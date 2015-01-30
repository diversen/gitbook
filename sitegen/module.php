<?php

// just playing around
class gitbook_sitegen {

    public function ajaxStatus($message, $done = false) {

        if (!$done) {
            ?>
            <img src="/images/load.gif" width="16" />
        <?php } ?>
        <h3><?= $message ?></h3>
        <?php
    }

    public function testAction() {
        echo "<script>hljs.initHighlightingOnLoad();</script>";
        //$values = $this->yamlAsAry(1);
        $repo = $this->get(1);
        $path = $this->repoPath($repo);
        echo $path = '/home/dennis/lovecraft';
        $files = $this->getFilesAry($path, '/*.md');
        //print_r($files);
        //die;
        //$file = $files[0];
        $md = file_get_contents($file);
        $parse = new Parsedown();
        echo $parse->text($md);
    }

    public function htmlAction() {
        $path = '/home/dennis/lovecraft';
        $files = $this->getFilesAry($path, '/*.md');

        $f = new file_string();
        echo $f->getLine($files[1]);
        //die;
        print_r($files);
        die;
        $file = $files[1];
        $md = file_get_contents($file);
        $parse = new Parsedown();
        echo $parse->text($md);
        die;
    }

    public function test2Action() {

        // parse commandline options with php 
        // command line options usaually start with - and --
        $str = "-s -S --cchapters=7 -V geometry:margin=1in -V documentclass=memoir -V lang=danish";

        $allow = array(
            // Produce typographically correct output, converting straight quotes to curly quotes 

            'S' => null,
            'smart' => null,
            // Specify the base level for headers (defaults to 1).
            'base-header-level' => null,
            // Produce output with an appropriate header and footer 
            's' => null,
            'standalone' => null,
            // Include an automatically generated table of contents
            'toc' => null,
            // Specify the number of section levels to include in the table of contents. The default is 3
            'toc-depth' => null,
            // Options are pygments (the default), kate, monochrome, espresso, zenburn, haddock, and tango.
            'highlight-style' => null,
            // Produce a standalone HTML file with no external dependencies
            'self-contained' => null,
            // Produce HTML5 instead of HTML4. 
            'html5' => null,
            // Treat top-level headers as chapters in LaTeX, ConTeXt, and DocBook output.
            'chapters' => null,
            // Number section headings in LaTeX, ConTeXt, HTML, or EPUB output.
            'N' => null,
            'number-sections' => null,
            // Link to a CSS style sheet. 
            'c' => null,
            'css' => null,
            // Use the specified CSS file to style the EPUB
            'epub-stylesheet' => null,
            'epub-chapter-level' => '1-6',
            // epub-embed-font
            'epub-embed-font' => null,
            'V' => array('geometry:margin', 'documentclass', 'lang'),
        );

        $o = new optValid();
        $ary = $o->split($str);
        $ary = $o->getAry($ary);
        $ary = $o->setSubVal($ary);
        $ok = $o->isValid($ary, $allow);
        if (!$ok) {
            print_r($o->errors);
        } else {
            echo "OK";
        }

        die;
        //print_r($ary); die;
    }

}
