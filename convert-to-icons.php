<?php

// Setup our storage array.
$candidate = array( 'filename' => '', 'width' => 0, 'height' => 0, 'type' => '' );

// Open the directory.
$d = dir( '.' );

// Loop through the directory entries.
while( false !== ( $entry = $d->read() ) ) {
	// Skip . and ..
	if( $entry !== '.' && $entry !== '..' ) {

		// Only look for jpg/png/gif images.
		$is_jpg = fnmatch( '*.jpg', $entry ) || fnmatch( '*.jpeg', $entry );
		$is_png = fnmatch( '*.png', $entry );
		$is_gif = fnmatch( '*.gif', $entry );

		if( $is_jpg || $is_png || $is_gif ) {
			// Build the file name with path, getimagesize() seems to like it that way :)
			$fname = getcwd() . '/' . $entry;

			// Get the image information.
			$size = getimagesize( $fname );

			// Parse out the width and height.
			$width 	= $size[0];
			$height = $size[1];

			// Now check to make sure that it's mostly square and a real image (aka not a 0 width).
			if( $width > ( $height * 0.9 ) && 
				$width < ( $height * 1.1 ) && 
				$width > 0 ) 
				{

				// If the new image is largeer than the current candidate, promote this image to be the new candidate.
				if( $width > $candidate['width'] ) {
					$candidate['filename'] 	= $fname;
					$candidate['width'] 	= $width;
					$candidate['height'] 	= $height;
					$candidate['type'] 		= $is_jpg ? 'jpg' : $is_png ? 'png' : 'gif';
				}
			}
		}
	}
}

// Close out the directory.
$d->close();

// Open the image file.
switch( $candidate['type'] ) {
	case 'jpg':
		$image = imagecreatefromjpeg( $candidate['filename'] );
		break;
	case 'png':
		$image = imagecreatefrompng( $candidate['filename'] );
		break;
	case 'gif':
		$image = imagecreatefromgif( $candidate['filename'] );
		break;
	}

// Scale the image down to a single pixel which should give us the background color.  
// Note that imagescale returns a new image and does not actually scale the original.
$bgcolor_image = imagescale( $image, 1, 1 );

// Get the color of the pixel that is left, zero based of course.
$bgcolor = imagecolorat( $bgcolor_image, 0, 0 );

// Create an RGBA array for later use of the color.
$rgba = imagecolorsforindex( $bgcolor_image, $bgcolor );

// Get rid of the temporary image we created.
imagedestroy( $bgcolor_image );

// Check to see if we're not a square, and if not, find the large or the two sides.
if( $candidate['width'] > $candidate['height'] ) 
	{ $placeholder_size = $candidate['width']; } 
else 
	{ $placeholder_size = $candidate['height']; }

// Create a new square placeholder image.
$placeholder_image = imagecreatetruecolor( $placeholder_size, $placeholder_size );

// Fill the new placeholder image with the background color.
imagefilledrectangle( $placeholder_image, 0, 0, $candidate['width'] - 1, $candidate['height'] - 1, $bgcolor );

// Determine the proper destination x index in case we didn't start off with a square.
if( $placeholder_size === $candidate['width'] ) { 
	$dest_x = 0; 
} 
else {
	$dest_x = ( $placeholder_size - $candidate['width'] ) / 2; 
}

// Determine the proper destination y index in case we didn't start off with a square.
if( $placeholder_size === $candidate['height'] ) {
	$dest_y = 0; 
}
else { 
	$dest_y = ( $placeholder_size - $candidate['height'] ) / 2; 
}

// Copy the source image to the placeholder image, this will fill in any transparent areas.
imagecopy( $placeholder_image, $image, $dest_x, $dest_y, 0, 0, $candidate['width'] - 1, $candidate['height'] - 1 );

// Write out the new placeholder image.
imagepng( $placeholder_image, 'placeholder.png' );

$mipmap_sizes   = array( 'mdpi' => 48, 'hdpi' => 72, 'xhdpi' => 96, 'xxhdpi' => 144, 'xxxhdpi' => 192 );
$drawable_sizes = array( 'mdpi' => 24, 'hdpi' => 36, 'xhdpi' => 48, 'xxhdpi' => 72,  'xxxhdpi' => 192 );

foreach( $mipmap_sizes as $type => $size ) {

	// Create the foreground mipmap image, transparent background.
	$mipmap = imagescale( $placeholder_image, $size, $size, IMG_NEAREST_NEIGHBOUR );

	// Set the transparency after the scaling, otherwise it will not work.
	imagecolortransparent( $mipmap, $bgcolor );

	// Write out the image
	imagepng( $mipmap, 'mipmap/mipmap-' . $type . '/ic_launcher_foreground.png' );

	imagedestroy( $mipmap );

	// Create the standard mipmap image, box with rounded corners.
	// First, calculate a border size.
	$border_size =  abs( ( $placeholder_size * 0.1 ) );
	// Make sure it's an even number to make the math better.
	if( $border_size % 2 !== 0 ) { $border_size + 1; }

	$mipmap = imagecreatetruecolor( $placeholder_size + $border_size, $placeholder_size + ( $placeholder_size * 0.1 ) );

	imageroundedrectangle( $mipmap, 0, 0, $placeholder_size + $border_size, $placeholder_size + $border_size, 30, $bgcolor );

	imagecopy( $mipmap, $placeholder_image, $border_size / 2, $border_size / 2, 0, 0, $placeholder_size - 1, $placeholder_size - 1 );

	// Set the transparency after the scaling, otherwise it will not work.
	imagecolortransparent( $mipmap, 0 );

	imagepng( $mipmap, 'mipmap/mipmap-' . $type . '/ic_launcher.png' );

	imagedestroy( $mipmap );

	// Create the round mipmap image, image in a circle.
	// First, find how big of circle we need to make.  This uses the two sides of the rectange and the 90 degree angle 
	// to calculate the height of the triangle for the radious.
	$radius = $placeholder_size * sin( 90 );
	$mipmap_size = $radius * 2;
	$mipmap = imagecreatetruecolor( $mipmap_size, $mipmap_size );

	imagefilledellipse( $mipmap, $radius, $radius, $mipmap_size, $mipmap_size, $bgcolor );

	$dest_x = ( $mipmap_size - $placeholder_size ) / 2;
	$dest_y = $dest_x;

	imagecopy( $mipmap, $placeholder_image, $dest_x, $dest_y, 0, 0, $placeholder_size - 1, $placeholder_size - 1 );

	// Set the transparency after the scaling, otherwise it will not work.
	imagecolortransparent( $mipmap, 0 );

	imagepng( $mipmap, 'mipmap/mipmap-' . $type . '/ic_launcher_round.png' );

	imagedestroy( $mipmap );
}

foreach( $drawable_sizes as $type => $size ) {
	$drawable = imagescale( $placeholder_image, $size, $size, IMG_NEAREST_NEIGHBOUR );

	// Set the transparency after the scaling, otherwise it will not work.
	imagecolortransparent( $drawable, $bgcolor );

	imagepng( $drawable, 'drawable/drawable-' . $type . '/ic_appbar.png' );

	imagedestroy( $drawable );
}

// From https://gist.github.com/mistic100/9301c0eebaef047bfdc8
function imageroundedrectangle( &$img, $x1, $y1, $x2, $y2, $r, $color ) {
    $r = min( $r, floor( min( ( $x2 - $x1 ) / 2, ( $y2 - $y1 ) / 2 ) ) );
    
    // render corners
    imagefilledarc( $img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE );
    imagefilledarc( $img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270,   0, $color, IMG_ARC_PIE );
    imagefilledarc( $img, $x2 - $r, $y2 - $r, $r * 2, $r * 2,   0,  90, $color, IMG_ARC_PIE );
    imagefilledarc( $img, $x1 + $r, $y2 - $r, $r * 2, $r * 2,   0, 180, $color, IMG_ARC_PIE );
    
    // middle fill, left fill, right fill
    imagefilledrectangle( $img, $x1 + $r, $y1,      $x2 - $r, $y2,      $color);
    imagefilledrectangle( $img, $x1,      $y1 + $r, $x1 + $r, $y2 - $r, $color);
    imagefilledrectangle( $img, $x2 - $r, $y1 + $r, $x2,      $y2 - $r, $color);
    
    return true;
}
