<?php

namespace aquy\thumbnail;

use Yii;
use yii\helpers\Html;
use yii\imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Color;
use Imagine\Image\Point;
use yii\helpers\FileHelper;
use Imagine\Image\Palette\RGB;
use Imagine\Image\ManipulatorInterface;

class Thumbnail
{
    const THUMBNAIL_OUTBOUND = ManipulatorInterface::THUMBNAIL_OUTBOUND;
    const THUMBNAIL_INSET = ManipulatorInterface::THUMBNAIL_INSET;
    const THUMBNAIL_KEEP_ASPECT_RATIO = 'keep_aspect_ratio';

    public static $cashBaseAlias = '@webroot';

    public static $cashWebAlias = '@web';

    public static $cacheAlias = 'assets/thumbnails';

    public static $cacheExpire = 0;

    public static $watermark;

    public static $watermarkConfig = [
        'fontFile' => '@aquy/thumbnail/fonts/OpenSans.ttf',
        'fontSize' => 16,
        'fontColor' => 'ffffff',
        'fontAlpha' => 50,
        'fontAngle' => 0,
        'fontStart' => [0,0]
    ];

    public static $quality = 85;

    public static $color = ['ffffff', 100];

    public static $padding = [30,12];

    public static $position = ['right', 'bottom'];

    public static function thumbnail($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $isWatermark = false, $watermarkConfig = array())
    {
        return Image::getImagine()->open(self::thumbnailFile($filename, $width, $height, $mode, $isWatermark, $watermarkConfig));
    }

    public static function thumbnailFile($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $isWatermark = false, $watermarkConfig = array())
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        if (!is_file($filename)) {
            throw new FileNotFoundException("File $filename doesn't exist");
        }
        $cachePath = Yii::getAlias(self::$cashBaseAlias . '/' . self::$cacheAlias);

        $thumbnailFileExt = strrchr($filename, '.');
        $thumbnailFileName = md5($filename . $width . $height . $mode . filemtime($filename));
        $thumbnailFilePath = $cachePath . DIRECTORY_SEPARATOR . substr($thumbnailFileName, 0, 2);
        $thumbnailFile = $thumbnailFilePath . DIRECTORY_SEPARATOR . $thumbnailFileName . $thumbnailFileExt;

        if (file_exists($thumbnailFile)) {
            if (self::$cacheExpire !== 0 && (time() - filemtime($thumbnailFile)) > self::$cacheExpire) {
                unlink($thumbnailFile);
            } else {
                return $thumbnailFile;
            }
        }
        if (!is_dir($thumbnailFilePath)) {
            mkdir($thumbnailFilePath, 0755, true);
        }

        $box = new Box($width, $height);
        $imagine = Image::getImagine();
        $image = $imagine->open($filename);
        if ($mode == self::THUMBNAIL_KEEP_ASPECT_RATIO) {
            $image = $image->thumbnail($box, self::THUMBNAIL_INSET);
            $sizeR = $image->getSize();
            $widthR = $sizeR->getWidth();
            $heightR = $sizeR->getHeight();
            $palette = new RGB();
            $color = $palette->color(self::$color[0], self::$color[1]);
            $preserve = $imagine->create($box, $color);
            $startX = $startY = 0;
            if ( $widthR < $width ) {
                $startX = ( $width - $widthR ) / 2;
            }
            if ( $heightR < $height ) {
                $startY = ( $height - $heightR ) / 2;
            }
            $image = $preserve->paste($image, new Point($startX, $startY));
        } else {
            $image = $image->thumbnail($box, $mode);
        }
        $image->save($thumbnailFile, ['quality' => self::$quality]);
        if ($isWatermark) {
            if (isset($watermarkConfig['watermark'])) {
                self::$watermark = $watermarkConfig['watermark'];
            }
            if (isset($watermarkConfig['padding'])) {
                self::$padding = $watermarkConfig['padding'];
            }
            if (isset($watermarkConfig['position'])) {
                self::$position = $watermarkConfig['position'];
            }
            if (file_exists(Yii::getAlias(self::$watermark))) {
                $watermark = Image::getImagine()->open(Yii::getAlias(self::$watermark));
                $image = Image::getImagine()->open($thumbnailFile);
                $size = $image->getSize();
                $wSize = $watermark->getSize();

                $point = array();

                switch (self::$position[0]) {
                    case 'right':
                        $point[0] = $size->getWidth() - $wSize->getWidth() - self::$padding[0];
                        break;
                    case 'center':
                        $point[0] = floor(($size->getWidth() - $wSize->getWidth())/2);
                        break;
                    default:
                        $point[0] = self::$padding[0];
                }

                switch (self::$position[1]) {
                    case 'bottom':
                        $point[1] = $size->getHeight() - $wSize->getHeight() - self::$padding[1];
                        break;
                    case 'center':
                        $point[1] = floor(($size->getHeight() - $wSize->getHeight())/2);
                        break;
                    default:
                        $point[1] = self::$padding[1];
                }

                $bottomRight = new Point($point[0], $point[1]);
                $image->paste($watermark, $bottomRight);
                $image->save($thumbnailFile, ['quality' => 100]);
            } else if (self::$watermark) {
                $point = new Point(
                    self::$watermarkConfig['fontStart'][0],
                    self::$watermarkConfig['fontStart'][1]
                );
                $color = new Color(
                    self::$watermarkConfig['fontColor'],
                    self::$watermarkConfig['fontSize']
                );
                $font = Image::getImagine()->font(
                    Yii::getAlias(self::$watermarkConfig['fontFile']),
                    Yii::getAlias(self::$watermarkConfig['fontSize']),
                    $color
                );
                $image = Image::getImagine()->open($thumbnailFile);
                $image->draw()->text(
                    self::$watermark,
                    $font,
                    $point,
                    self::$watermarkConfig['fontAngle']
                );
                $image->save($thumbnailFile, ['quality' => 100]);
            }
        }
        return $thumbnailFile;
    }

    public static function thumbnailFileUrl($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $isWatermark = false, $watermarkConfig = array())
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        $cacheUrl = Yii::getAlias(self::$cashWebAlias .'/' . self::$cacheAlias);
        $thumbnailFilePath = self::thumbnailFile($filename, $width, $height, $mode, $isWatermark, $watermarkConfig);

        preg_match('#[^\\' . DIRECTORY_SEPARATOR . ']+$#', $thumbnailFilePath, $matches);
        $fileName = $matches[0];

        return $cacheUrl . '/' . substr($fileName, 0, 2) . '/' . $fileName;
    }

    public static function thumbnailImg($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $options = [], $isWatermark = false, $watermarkConfig = array())
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        try {
            $thumbnailFileUrl = self::thumbnailFileUrl($filename, $width, $height, $mode, $isWatermark, $watermarkConfig);
        } catch (FileNotFoundException $e) {
            return 'File doesn\'t exist';
        } catch (\Exception $e) {
            Yii::warning("{$e->getCode()}\n{$e->getMessage()}\n{$e->getFile()}");
            return 'Error ' . $e->getCode();
        }

        return Html::img(
            $thumbnailFileUrl,
            $options
        );
    }

    public static function clearCache()
    {
        $cacheDir = Yii::getAlias(self::$cashBaseAlias . '/' . self::$cacheAlias);
        self::removeDir($cacheDir);
        return @mkdir($cacheDir, 0755, true);
    }

    protected static function removeDir($path)
    {
        if (is_file($path)) {
            @unlink($path);
        } else {
            array_map('self::removeDir', glob($path . DIRECTORY_SEPARATOR . '*'));
            @rmdir($path);
        }
    }
}