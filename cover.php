<?php

$vendor = dirname(__FILE__) . "/vendor";
require "$vendor/autoload.php";

use GDText\Box;
use GDText\Color;

class gitbook_cover extends gitbook {

    public function create($id) {

        $yaml = $this->yamlAsAry($id);
        $save = _COS_HTDOCS . "/books/$id/cover.png";

        $title = substr($yaml['title'], 0, 60);

        $im = imagecreatetruecolor(1800, 2400);
        $backgroundColor = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $backgroundColor);

        $box = new Box($im);

        $font = config::getModulePath('gitbook') . "/fonts/OpenSans-ExtraBold.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(0, 0, 0));
        //$box->setTextShadow(new Color(0, 0, 0, 50), 2, 2);
        $box->setFontSize(80);
        $box->setLineHeight(1.5);
        $box->setBox(100, 700, 1600, 2300);
        $box->setTextAlign('center', 'top');

        $box->draw(
                $title
        );

        $font = config::getModulePath('gitbook') . "/fonts/OpenSans-Regular.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(33, 33, 33));

        $box->setFontSize(40);
        $box->setLineHeight(1.5);
        $box->setBox(200, 1000, 1400, 2300);
        $box->setTextAlign('center', 'top');

        $sub = substr($yaml['Subtitle'], 0, 255);
        $box->draw(
                $sub
        );

        $authors = '';
        foreach ($yaml['author'] as $a) {
            $authors.="$a\n";
        }
//die;
        $authors = substr($authors, 0, 255);
        $font = config::getModulePath('gitbook') . "/fonts/OpenSans-ExtraBold.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(33, 33, 33));

        $box->setFontSize(80);
        $box->setLineHeight(1.5);
        $box->setBox(200, 1400, 1400, 2300);
        $box->setTextAlign('center', 'top');

        
        $box->draw(
                $authors
        );

        //imagepng($im, $save, 0, PNG_ALL_FILTERS);
        imagepng($im, $save);

    }
    
    public function scale () {
        
    }
}
