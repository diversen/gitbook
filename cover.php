<?php

$vendor = dirname(__FILE__) . "/vendor";
require "$vendor/autoload.php";

use GDText\Box;
use GDText\Color;
use Gregwar\Image\Image;

class gitbook_cover extends gitbook {

    public function create($id) {

        $yaml = $this->yamlAsAry($id);
        $save = _COS_HTDOCS . "/books/$id/cover.png";
        $title = mb_substr($yaml['title'], 0, 60);
        
        $im = imagecreatetruecolor(1800, 2400);
        $backgroundColor = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $backgroundColor);

        $box = new Box($im);

        $font = config::getModulePath('gitbook') . "/fonts/OpenSans-Bold.ttf";

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

        $font = config::getModulePath('gitbook') . "/fonts/OpenSans-Regular.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(33, 33, 33));
        $box->setFontSize(60);
        $box->setLineHeight(1.5);
        $box->setBox(200, 900, 1400, 2300);
        $box->setTextAlign('center', 'top');
        
        $sub = mb_substr($yaml['Subtitle'], 0, 255);
        $box->draw($sub);

        $authors = '';
        foreach ($yaml['author'] as $a) {
            $authors.="$a\n";
        }

        $authors = mb_substr($authors, 0, 255);
        $font = config::getModulePath('gitbook') . "/fonts/OpenSans-Bold.ttf";

        $box->setFontFace($font); // http://www.dafont.com/franchise.font
        $box->setFontColor(new Color(33, 33, 33));
        $box->setFontSize(80);
        $box->setLineHeight(1.5);
        $box->setBox(200, 1500, 1400, 2300);
        $box->setTextAlign('center', 'top');
        $box->draw($authors);

        //imagepng($im, $save, 0, PNG_ALL_FILTERS);
        imagepng($im, $save);
    }
    
    public function testAction () {
        $save = _COS_HTDOCS . "/files/cover.jpg";

        $font = _COS_HTDOCS . "/fonts/captcha.ttf";
        $image = config::getModulePath('gitbook') . "/images/white.jpg";
        
        // ration 600 x 800
        $text = "Her er en noget længere tekst";
        
        //$text.= $text . $text;
        Image::open($image)
            ->resize(600, 800)
            //->write($font, $text, 150, 150, 20, 0, '#000', 'left')
            ->save($save);
    }
    
    public function scale ($id, $image) {
        
        $assets_dir = $this->exportsDir($id) . "/assets";
        file::mkdirDirect($assets_dir);

        $parts = pathinfo($image);
        $save = $assets_dir . '/scaled-' . $parts['basename'];
        //$yaml['cover-image'] = $cover_image;
        
        $bg = 0xffffff;
        Image::open($image)->
            scaleResize(1800 / 6, 2400 / 6, $bg)->
            save($save);        
    }
}
