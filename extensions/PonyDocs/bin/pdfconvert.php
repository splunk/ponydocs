<?php
/**
 * Utility script used to convert an input PDF file to 
 *
*/
// Ponydocs environment configuration
define('PONYDOCS_PDF_COPYRIGHT_MESSAGE', 'Created Using PonyDocs Mediawiki Extension: http://splunk.github.com/ponydocs/');
define('PONYDOCS_WKHTMLTOPDF_PATH', dirname(__FILE__) . '/wkhtmltopdf-amd64');

// Path used to created temporary files for processing
$path = '/tmp/';

// These are the getopt options that need to be specified on the command line.
$options = getopt('', array(
							'cover:',
							'file:',
							'output:',
							'encoding:',
							'copyright:'
	));

// If required variables are not provided, print usage and exit with err.
if (!isset($options['file']) || !isset($options['output'])) {
	print_usage();
	return -1;
}

$file = $options['file'];
$pdfFileName = $options['output'];
$coverFile = null;

if (!file_exists($file)) {
	print("Error: Input file " . $file . " does not exist.\n\n");
	return -1;
}

// Setup other args
$copyright = isset($options['copyright']) ? $options['copyright'] : PONYDOCS_PDF_COPYRIGHT_MESSAGE;
$encoding = isset($options['encoding']) ? $options['encoding'] : 'utf-8';

// If a cover file was specified, let's do some setup of the cover page.
if (isset($options['cover'])) {
	$coverFile = $options['cover'];
	if (!file_exists($coverFile)) {
		print("Error: Cover file " . $cover . " does not exist.\n\n");
		return -1;
	}
	$file_contents = file_get_contents($coverFile);
	print("Generating Title Page.\n");

	// Okay, create the title page
	$titlepagefile = "$path/" .uniqid('ponydocs-pdf-book-title') . '.html';
	$fh = fopen($titlepagefile, 'w+');

	$titleText = '<!doctype html><html lang="en" xmlns="http://www.w3.org/1999/xhtml" charset="' . $encoding . '"> '
		. '<head> <meta charset="' . $encoding . '"> <title></title> '
		. '<style> html,body { margin: 0px; padding: 0px; width: 210mm; max-width: 210mm; overflow-x: hidden; } </style> '
		. '</head> <body>';
	$titleText .= $file_contents . "</body></html>"; 
	fwrite($fh, $titleText); 
	fclose($fh); 
}
	
// Create main input file
$file = $path . "/" . $file; 
$file_contents = file_get_contents($file);

$html = ' <!doctype html> <html lang="en" xmlns="http://www.w3.org/1999/xhtml" charset="' . $encoding . '"> '
	. '<head> <meta charset="' . $encoding . '"> <title></title> '
	. '<style> html,body { margin: 0px; padding: 0px; width: 210mm; max-width: 210mm; overflow-x: hidden; } '
	. 'pre { width: 100%; overflow-x: hidden; } </style> </head> <body>';
$html .= $file_contents;
$html .= "</body></html>";

$file = "$path/" . uniqid('ponydocs-pdf-book') . '.html';
$fh = fopen($file, 'w+');
fwrite($fh, $html);
fclose($fh);

// Build wkhtmltopdf command.
if($coverFile) {
	$cmd = PONYDOCS_WKHTMLTOPDF_PATH . ' --encoding ks_c_5601-1987 --enable-internal-links --load-error-handling skip '
		. '--footer-font-size 10 --margin-bottom 25.4mm --margin-top 25.4mm --margin-left 31.75mm --margin-right 31.75mm cover '
		. $titlepagefile . ' --footer-left "' . PONYDOCS_PDF_COPYRIGHT_MESSAGE . '" --exclude-from-outline toc --xsl-style-sheet '
		. dirname(__FILE__) . '/toc.xsl --exclude-from-outline ' . $file . ' --encoding ks_c_5601-1987 --footer-center "[page]" '
		. '--zoom 1.03 ' . $pdfFileName;
} else {
	$cmd = PONYDOCS_WKHTMLTOPDF_PATH . ' --encoding ks_c_5601-1987 --enable-internal-links --load-error-handling skip '
		. '--footer-font-size 10 --margin-bottom 25.4mm --margin-top 25.4mm --margin-left 31.75mm --margin-right 31.75mm '
		. $file . ' --encoding ks_c_5601-1987 --footer-center "[page]" --zoom 1.03 ' . $pdfFileName;
}

$output = array();
$returnVar = 0;
print("Executing wkhtmltopdf on requested file.\n");
exec($cmd, $output, $returnVar);


print("Cleaning up\n");

@unlink($file);
@unlink($titlepagefile);

/**
 * Prints usage of pdfconvert on command line.
 */
function print_usage() {
print <<<EOT
Usage: pdfconvert.php --file <input_file> --output <output_file> [optional arguments]

Required Arguments:

 --file <input_file>		The input html file. Do not include HTML header or body element.
 --output <output_file>		The output PDF filename.

Optional Arguments:

 --cover <cover_file>		The cover html file to provide as a cover page. Do not include HTML header or body element.
 --copyright <copyright>	Override the copyright message on coverpage to something else.
 --encoding <charset>		The encoding character set input files use (default: utf-8)


EOT;
}

