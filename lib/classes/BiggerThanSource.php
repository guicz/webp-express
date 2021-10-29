<?php

/*
This class is made to not be dependent on Wordpress functions and must be kept like that.
It is used by webp-on-demand.php. It is also used for bulk conversion.
*/
namespace WebPExpress;


class BiggerThanSource
{


    /**
     * Create the directory for log files and put a .htaccess file into it, which prevents
     * it to be viewed from the outside (not that it contains any sensitive information btw, but for good measure).
     *
     * @param  string  $logDir  The folder where log files are kept
     *
     * @return boolean  Whether it was created successfully or not.
     *
     */
    private static function createBiggerThanSourceBaseDir($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            @chmod($dir, 0775);
            @file_put_contents(rtrim($dir . '/') . '/.htaccess', <<<APACHE
<IfModule mod_authz_core.c>
Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
Order deny,allow
Deny from all
</IfModule>
APACHE
            );
            @chmod($dir . '/.htaccess', 0664);
        }
        return is_dir($dir);
    }

    public static function pathToDummyFile($source, $basedir, $imageRoots, $destinationFolder, $destinationExt)
    {
        $sourceResolved = realpath($source);

        // Check roots until we (hopefully) get a match.
        // (that is: find a root which the source is inside)
        foreach ($imageRoots->getArray() as $i => $imageRoot) {
            $rootPath = $imageRoot->getAbsPath();

            // We can assume that $rootPath is resolvable using realpath (it ought to exist and be within open_basedir for WP to function)
            // We can also assume that $source is resolvable (it ought to exist and within open_basedir)
            // So: Resolve both! and test if the resolved source begins with the resolved rootPath.
            if (strpos($sourceResolved, realpath($rootPath)) !== false) {
                $relPath = substr($sourceResolved, strlen(realpath($rootPath)) + 1);
                $relPath = ConvertHelperIndependent::appendOrSetExtension($relPath, $destinationFolder, $destinationExt, false);

                return $basedir . '/' . $imageRoot->id . '/' . $relPath;
                break;
            }
        }
        return false;
    }

    /**
     * Saves the log file corresponding to a conversion.
     *
     * @param  string  $source   Path to the source file that was converted
     *
     *
     */
    public static function updateStatus($source, $destination, $webExpressContentDirAbs, $imageRoots, $destinationFolder, $destinationExt)
    {
        $basedir = $webExpressContentDirAbs . '/webp-images-bigger-than-source';

        if (!file_exists($basedir)) {
            self::createBiggerThanSourceBaseDir($basedir);
        }
        $filesizeDestination = @filesize($destination);
        $filesizeSource = @filesize($source);
        $bigWebP = false;
        if (
            ($filesizeSource !== false) &&
            ($filesizeDestination !== false) &&
            ($filesizeDestination > $filesizeSource)
        ) {
          $bigWebP = true;
        }

        $file = self::pathToDummyFile($source, $basedir, $imageRoots, $destinationFolder, $destinationExt);
        if ($file === false) {
            return;
        }

        if ($bigWebP) {
            // place dummy file, which marks that webp is bigger than source

            $folder = @dirname($file);
            if (!@file_exists($folder)) {
                mkdir($folder, 0777, true);
            }
            if (@file_exists($folder)) {
                file_put_contents($file, '');
            }

        } else {
            // remove dummy file (if exists)
            if (@file_exists($file)) {
                @unlink($file);
            }
        }

    }

}
