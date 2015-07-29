<?php


$vendor = dirname(__FILE__) . "/vendor";
require "$vendor/autoload.php";

use GDText\Box;
use GDText\Color;
use Gregwar\Image\Image;


class gittobook_cover extends gittobook {

    public function create($id) {

        $yaml = $this->yamlAsAry($id);
        $save = _COS_HTDOCS . "/books/$id/cover.png";
        $title = mb_substr($yaml['title'], 0, 60);
        
        $im = imagecreatetruecolor(1800, 2400);
        $backgroundColor = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $backgroundColor);

        $box = new Box($im);

        $font = conf::getModulePath('gittobook') . "/fonts/OpenSans-Bold.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(0, 0, 0));
        //$box->setTextShadow(new Color(0, 0, 0, 50), 2, 2);
        $box->setFontSize(80);
        $box->setLineHeight(1.5);
        $box->setBox(100, 600, 1600, 2300);
        $box->setTextAlign('center', 'top');

        $box->draw(
                $title
        );

        $font = conf::getModulePath('gittobook') . "/fonts/OpenSans-Regular.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(33, 33, 33));
        $box->setFontSize(60);
        $box->setLineHeight(1.5);
        $box->setBox(200, 900, 1400, 2200);
        $box->setTextAlign('center', 'top');
        
        $sub = mb_substr($yaml['Subtitle'], 0, 255);
        $box->draw($sub);

        $authors = '';
        foreach ($yaml['author'] as $a) {
            $authors.="$a\n";
        }

        $authors = mb_substr($authors, 0, 255);
        $font = conf::getModulePath('gittobook') . "/fonts/OpenSans-Bold.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(33, 33, 33));
        $box->setFontSize(80);
        $box->setLineHeight(1.5);
        $box->setBox(200, 1500, 1400, 2200);
        $box->setTextAlign('center', 'top');
        $box->draw($authors);

        //imagepng($im, $save, 0, PNG_ALL_FILTERS);
        imagepng($im, $save);
    }
    
    /**
     * scale image
     * @param int $id
     * @param string $image
     * @return string $save full save path of image
     */
    
    public function scale ($id, $image) {
        
        $assets_dir = $this->exportsDir($id) . "/assets";
        file::mkdirDirect($assets_dir);

        $parts = pathinfo($image);
        $save = $assets_dir . '/scaled-' . $parts['basename'];
        
        $bg = 0xffffff;
        Image::open($image)->
            scaleResize(1800 / 2, 2400 / 2, $bg)->
            save($save);
        
        return "/books/$id/assets/scaled-" . $parts['basename'];
    }
}
