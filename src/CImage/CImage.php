<?php

class CImage
{
	private $max_width = 2000;
	private $max_height = 2000;
	private $img_path;
	private $cache_path;
	private $width;
	private $height;
	private $filesize;
	private $verbose;
	private $cropToFit;
	private $cropWidth;
	private $cropHeight;
	private $newWidth;
	private $newHeight;
	private $src;
	private $saveAs;
	private $quality;
	private $ignoreCache;
	private $sharpen;
	private $pathToImage;
	private $cacheFileName;
	private $fileExtension;
	private $image;
    private $type = null;
    private $attr = null;

	public function __construct($dir)
	{
		//
		// Ensure error reporting is on
		//
		error_reporting(-1);              // Report all type of errors
		ini_set('display_errors', 1);     // Display all errors
		ini_set('output_buffering', 0);   // Do not buffer outputs, write directly

		// Define some constant values, append slash
		// Use DIRECTORY_SEPARATOR to make it work on both windows and unix.
		//
		$this->img_path = $dir .
			DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;
		$this->cache_path = $dir .
			DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
	}

	public function build_and_show_image($options)
	{
		$this->handle_options($options);
		$this->verbose_header();
		$this->get_image_information();
		$this->calculate_new_size();
		$this->handle_caching();
		$this->build_processed_image();
		$this->verbose_footer();
		$this->outputImage();
	}

	//
	// Get the incoming arguments
	//
	private function handle_options($options)
	{
		$this->cropToFit   = isset($options['crop-to-fit']) ? true : null;
		$this->newWidth    = isset($options['width'])   ? $options['width']    : null;
		$this->newHeight   = isset($options['height'])  ? $options['height']   : null;
		$this->src         = isset($options['src'])     ? $options['src']      : null;
		$this->verbose     = isset($options['verbose']) ? true              : null;
		$this->saveAs      = isset($options['save-as']) ? $options['save-as']  : null;
		$this->quality     = isset($options['quality']) ? $options['quality']  : 60;
		$this->ignoreCache = isset($options['no-cache']) ? true           : null;
		$this->sharpen     = isset($options['sharpen']) ? true : null;
		$this->pathToImage = realpath($this->img_path . $this->src);

		//
		// Validate incoming arguments
		//
		is_dir($this->img_path) or
			$this->errorMessage('The image dir is not a valid directory.');
		is_writable($this->cache_path) or
			$this->errorMessage('The cache dir is not a writable directory.');
		isset($this->src) or
			$this->errorMessage('Must set src-attribute.');
		preg_match('#^[a-z0-9A-Z-_\.\/]+$#', $this->src) or
			$this->errorMessage('Filename contains invalid characters.');
		substr_compare($this->img_path, $this->pathToImage, 0, strlen($this->img_path)) == 0 or
			$this->errorMessage("Security constraint: Source image is not directly below the directory {$this->img_path}.");
		is_null($this->saveAs) or in_array($this->saveAs, array('png', 'jpg', 'jpeg')) or
				$this->errorMessage('Not a valid extension to save image as');
		is_null($this->quality) or
			(is_numeric($this->quality) and $this->quality > 0 and $this->quality <= 100) or
			$this->errorMessage('Quality out of range');
		is_null($this->newWidth) or
			(is_numeric($this->newWidth) and $this->newWidth > 0 and $this->newWidth <= $this->max_width) or
			$this->errorMessage('Width out of range');
		is_null($this->newHeight) or
			(is_numeric($this->newHeight) and $this->newHeight > 0 and $this->newHeight <= $this->max_height) or
			$this->errorMessage('Height out of range');
		is_null($this->cropToFit) or ($this->cropToFit and $this->newWidth and $this->newHeight) or
			$this->errorMessage('Crop to fit needs both width and height to work');
	}

	//
	// Start displaying log if verbose mode & create url to current image
	//
	private function verbose_header()
	{
		if (!$this->verbose)
			return;
		$query = array();
		parse_str($_SERVER['QUERY_STRING'], $query);
		unset($query['verbose']);
		$url = '?' . http_build_query($query);

		echo <<<EOD
<html lang='en'>
  <meta charset='UTF-8'/>
  <title>img.php verbose mode</title>
  <h1>Verbose mode</h1>
  <p>
    <a href='$url'><code>$url</code></a><br>
    <img src='{$url}' />
  </p>
EOD;
	}

	private function verbose_footer()
	{
		if (!$this->verbose)
			return;
		clearstatcache();
		$cacheFilesize = filesize($this->cacheFileName);
		$this->verbose_log("File size of cached file: {$cacheFilesize} bytes.");
		$this->verbose_log("Cache file has a file size of " .
			round($cacheFilesize / $this->filesize*100) .
			"% of the original size.");
	}

	/**
	 * Display error message.
	 *
	 * @param string $message the error message to display.
	 */
	private function errorMessage($message)
	{
		header("Status: 404 Not Found");
		die('CImage.php says 404 - ' . htmlentities($message));
	}

	/**
	 * Display log message.
	 *
	 * @param string $message the log message to display.
	 */
	private function verbose_log($message)
	{
		echo "<p>" . htmlentities($message) . "</p>";
	}

	//
	// Get information on the image
	//
	private function get_image_information()
	{
		$imgInfo = list($width, $height, $type, $attr) = getimagesize($this->pathToImage);
		!empty($imgInfo) or $this->errorMessage("The file doesn't seem to be an image.");
		$this->width = $width;
		$this->height = $height;
		$this->type = $type;
		$this->attr = $attr;
		$mime = $imgInfo['mime'];

		if ($this->verbose) {
			$this->filesize = filesize($this->pathToImage);
			$this->verbose_log("Image file: {$this->pathToImage}");
			$this->verbose_log("Image information: " . print_r($imgInfo, true));
			$this->verbose_log("Image width x height (type): {$width} x {$height} ({$type}).");
			$this->verbose_log("Image file size: {$this->filesize} bytes.");
			$this->verbose_log("Image mime type: {$mime}.");
		}
	}

	//
	// Calculate new width and height for the image
	//
	private function calculate_new_size()
	{
		$aspectRatio = $this->width / $this->height;

		if ($this->cropToFit && $this->newWidth && $this->newHeight) {
			$targetRatio = $this->newWidth / $this->newHeight;
			$this->cropWidth   = $targetRatio > $aspectRatio ? $this->width : round($this->height * $targetRatio);
			$this->cropHeight  = $targetRatio > $aspectRatio ? round($this->width  / $targetRatio) : $this->height;
			if ($this->verbose) {
				$this->verbose_log("Crop to fit into box of {$this->newWidth}x{$this->newHeight}." .
					" Cropping dimensions: {$this->cropWidth}x{$this->cropHeight}.");
			}
		}
		else if ($this->newWidth && !$this->newHeight) {
			$this->newHeight = round($this->newWidth / $aspectRatio);
			if ($this->verbose) {
				$this->verbose_log("New width is known {$this->newWidth}, height is calculated to {$this->newHeight}.");
			}
		} else if (!$this->newWidth && $this->newHeight) {
			$this->newWidth = round($this->newHeight * $aspectRatio);
			if ($this->verbose) {
				$this->verbose_log("New height is known {$this->newHeight}, width is calculated to {$this->newWidth}.");
			}
		} else if ($this->newWidth && $this->newHeight) {
			$ratioWidth  = $this->width  / $this->newWidth;
			$ratioHeight = $this->height / $this->newHeight;
			$ratio = ($ratioWidth > $ratioHeight) ? $ratioWidth : $ratioHeight;
			$this->newWidth  = round($this->width  / $ratio);
			$this->newHeight = round($this->height / $ratio);
			if ($this->verbose) {
				$this->verbose_log("New width & height is requested, keeping aspect ratio results in {$this->newWidth}x{$this->newHeight}.");
			}
		} else {
			$this->newWidth = $this->width;
			$this->newHeight = $this->height;
			if ($this->verbose) {
				$this->verbose_log("Keeping original width & height.");
			}
		}
	}

	//
	// Creating a filename for the cache
	//
	private function handle_caching()
	{
		$parts          = pathinfo($this->pathToImage);
		$this->fileExtension  = $parts['extension'];
		$this->saveAs         = is_null($this->saveAs) ? $this->fileExtension : $this->saveAs;

		$quality_       = is_null($this->quality) ? null : "_q{$this->quality}";
		$cropToFit_     = is_null($this->cropToFit) ? null : "_cf";
		$sharpen_       = is_null($this->sharpen) ? null : "_s";
		$dirName        = preg_replace('/\//', '-', dirname($this->src));
		$this->cacheFileName = $this->cache_path .
			"-{$dirName}-{$parts['filename']}_{$this->newWidth}_{$this->newHeight}{$quality_}{$cropToFit_}{$sharpen_}.{$this->saveAs}";
		$this->cacheFileName = preg_replace('/^a-zA-Z0-9\.-_/', '', $this->cacheFileName);

		if ($this->verbose) {
			$this->verbose_log("Cache file is: {$this->cacheFileName}");
		}

		//
		// Is there already a valid image in the cache directory, then use it and exit
		//
		$imageModifiedTime = filemtime($this->pathToImage);
		$cacheModifiedTime = is_file($this->cacheFileName) ? filemtime($this->cacheFileName) : null;

		// If cached image is valid, output it.
		if (!$this->ignoreCache && is_file($this->cacheFileName) && $imageModifiedTime < $cacheModifiedTime) {
			if ($this->verbose) {
				$this->verbose_log("Cache file is valid, output it.");
			}
			$this->outputImage();
		} else {
			if ($this->verbose) {
				$this->verbose_log("Cache is not valid, process image and create a cached version of it.");
			}
		}
	}

	/**
	 * Sharpen image as http://php.net/manual/en/ref.image.php#56144
	 * http://loriweb.pair.com/8udf-sharpen.html
	 *
	 * @return resource $image as the processed image.
	 */
	private function sharpenImage()
	{
		$matrix = array(
				array(-1,-1,-1,),
				array(-1,16,-1,),
				array(-1,-1,-1,)
				);
		$divisor = 8;
		$offset = 0;
		imageconvolution($this->image, $matrix, $divisor, $offset);
	}

	//
	// Open up the original image from file
	//
	private function load_image()
	{
		if ($this->verbose) {
			$this->verbose_log("File extension is: {$this->fileExtension}");
		}
		switch ($this->fileExtension) {
			case 'jpg':
			case 'jpeg':
				$this->image = imagecreatefromjpeg($this->pathToImage);
				if ($this->verbose) {
					$this->verbose_log("Opened the image as a JPEG image.");
				}
				break;

			case 'png':
				$this->image = imagecreatefrompng($this->pathToImage); 
				if ($this->verbose) {
					$this->verbose_log("Opened the image as a PNG image.");
				}
				break;  

			default:
				$this->errorMessage('No support for this file extension.');
		}
	}

	/**
	 * Create new image and keep transparency
	 *
	 * @param integer $width the new image width.
     * @param integer $height the new image height.
	 * @return resource $image as the processed image.
	 */
	private function createImageKeepTransparency($width, $height)
	{
		$img = imagecreatetruecolor($width, $height);
		imagealphablending($img, false);
		imagesavealpha($img, true);
		return $img;
	}

	//
	// Resize the image if needed
	//
	private function resize_and_crop()
	{
		if ($this->cropToFit) {
			if ($this->verbose) {
				$this->verbose_log("Resizing, crop to fit.");
			}
			$cropX = round(($this->width - $this->cropWidth) / 2);  
			$cropY = round(($this->height - $this->cropHeight) / 2);    
			$imageResized = $this->createImageKeepTransparency($this->newWidth, $this->newHeight);
			imagecopyresampled($imageResized, $this->image, 0, 0, $cropX, $cropY,
				$this->newWidth, $this->newHeight, $this->cropWidth, $this->cropHeight);
			$this->image = $imageResized;
			$this->width = $this->newWidth;
			$this->height = $this->newHeight;
		} else if (!($this->newWidth == $this->width && $this->newHeight == $this->height)) {
			if ($this->verbose) {
				$this->verbose_log("Resizing, new height and/or width.");
			}
			$imageResized = $this->createImageKeepTransparency($this->newWidth, $this->newHeight);
			imagecopyresampled($imageResized, $this->image, 0, 0, 0, 0,
				$this->newWidth, $this->newHeight, $this->width, $this->height);
			$this->image  = $imageResized;
			$this->width  = $this->newWidth;
			$this->height = $this->newHeight;
		}
	}

	//
	// Save the image
	//
	private function save_image()
	{
		switch($this->saveAs) {
			case 'jpeg':
			case 'jpg':
				if ($this->verbose) {
					$this->verbose_log("Saving image as JPEG to cache using quality = {$this->quality}.");
				}
				imagejpeg($this->image, $this->cacheFileName, $this->quality);
				break;  

			case 'png':  
				if ($this->verbose) {
					$this->verbose_log("Saving image as PNG to cache as {$this->cacheFileName}.");
				}
				// Turn off alpha blending and set alpha flag
				imagealphablending($this->image, false);
				imagesavealpha($this->image, true);
				imagepng($this->image, $this->cacheFileName);  
				break;

			default:
				$this->errorMessage('No support to save as file extension "' . $this->saveAs . '".');
				break;
		}
	}

	private function build_processed_image()
	{
		$this->load_image();

		$this->resize_and_crop();

		//
		// Apply filters and postprocessing of image
		//
		if ($this->sharpen) {
			$this->sharpenImage();
		}

		$this->save_image();
	}

	/**
	 * Output an image together with last modified header.
	 */
	private function outputImage()
	{
		$info = getimagesize($this->cacheFileName);
		!empty($info) or $this->errorMessage("The file doesn't seem to be an image.");
		$mime   = $info['mime'];

		$lastModified = filemtime($this->cacheFileName);
		$gmdate = gmdate("D, d M Y H:i:s", $lastModified);

		if ($this->verbose) {
			$this->verbose_log("Memory peak: " . round(memory_get_peak_usage() /1024/1024) . "M");
			$this->verbose_log("Memory limit: " . ini_get('memory_limit'));
			$this->verbose_log("Time is {$gmdate} GMT.");
		}

		if (!$this->verbose)
			header('Last-Modified: ' . $gmdate . ' GMT');
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
				strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModified){
			if ($this->verbose) {
				$this->verbose_log("Would send header 304 Not Modified, but its verbose mode."); exit;
			}
			header('HTTP/1.0 304 Not Modified');
		} else {
			$size = filesize($this->cacheFileName);
			if ($this->verbose) {
				$this->verbose_log("Would send header to deliver {$size} bytes for image {$this->cacheFileName} with modified time: {$gmdate} GMT and content-type {$mime}, but its verbose mode.");
			} else {
				header('Content-Length: ' . $size);
				header('Content-type: ' . $mime);
				readfile($this->cacheFileName);
			}
		}
		exit;
	}

}

