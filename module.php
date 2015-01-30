<?php

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Filesystem\Filesystem;
use diversen\valid;
use diversen\db\rb as db_rb;
use diversen\html;
use diversen\html\helpers as html_helpers;
use diversen\cli\optValid;
//use diversen\file\string as file_string;

class gitbook {

    /**
     * connect to database
     */
    public function __construct() {
        db_rb::connect();
    }

    /**
     * list actions public
     */
    public function indexAction() {
        echo "List repos";
    }

    /**
     * 
     * @param type $path
     * @param type $id
     * @return int
     */
    public function checkAccess($path, $id = null) {
        if ($path == 'repos') {
            if (!session::checkAccessClean('user')) {
                moduleloader::setStatus(403);
                return 0;
            }
        }
        return 1;
    }

    /*
     * action for /gitbook/repos
     */

    public function reposAction() {
        if (!$this->checkAccess('repos')) {
            return;
        }

        $bean = db_rb::getBean('gitrepo', 'user_id', session::getUserId());
        if ($bean->id) {
            $user_id = session::getUserId();
            $rows = db_q::select('gitrepo')->filter('user_id =', $user_id)->fetch();
            $rows = html::specialEncode($rows);
            echo $this->viewRepos($rows);
        }

        echo $this->viewAddRepo();
    }

    public function viewRepos($rows) {

        $str = '';
        foreach ($rows as $row) {
            $str.= html::createLink($row['repo'], html::getHeadline($row['repo']));
            $str.= $this->repoOptions($row);
            $str.= MENU_SUB_SEPARATOR_SEC;
            $str.= $this->viewExports($row['id']);
        }

        return $str;
    }

    public function repoOptions($row) {

        $str = '';
        $str.= html::createLink("/gitbook/delete?id=$row[id]&delete=1", lang::translate('Delete'));
        $str.= MENU_SUB_SEPARATOR;
        $str.= html::createLink("/gitbook/checkout?id=$row[id]", lang::translate('Checkout'));
        return $str;
    }

    /**
     * add repo to db
     * @return type
     */
    public function dbAddRepo() {
        $title = html::specialDecode($_POST['repo']);
        $bean = db_rb::getBean('gitrepo', 'repo', $title);
        $bean->uniqid = md5(uniqid('', true));
        $bean->name = $this->repoName($title);
        $bean->repo = $title;
        $bean->date = date::getDateNow(array('hms' => true));
        $bean->user_id = session::getUserId();
        return db_rb::commitBean($bean);
    }

    /**
     * list repos action
     */
    public function listRepos() {
        
    }

    /**
     * delete repo action
     * @return type
     */
    public function deleteAction() {
        if (!user::ownID('gitrepo', $_GET['id'], session::getUserId())) {
            moduleloader::setStatus(403);
            return;
        }

        if (isset($_POST['submit'])) {
            $this->delete($_GET['id']);
            http::locationHeader('/gitbook/repos', lang::translate('Repo has been deleted'));
        }

        echo html_helpers::confirmDeleteForm('submit', lang::translate('Confirm removal of repo'));
    }

    public function delete($id) {
        $private_path = $this->fileRepoPath($id, 'file');
        $public_path = config::getFullFilesPath() . $this->exportsDir($id);
        file::rrmdir($private_path);
        file::rrmdir($public_path);
        db_q::delete('gitrepo')->filter('id =', $id)->exec();
    }

    /**
     * form for adding the repo
     * @return type
     */
    public function addForm() {
        $f = new html();
        $f->init(null, 'repo_add', true);
        $f->formStart();
        $f->legend(lang::translate("Add a git repo"));
        $f->label('repo', lang::translate('Enter repo URL (http|https)'));
        $f->text('repo');
        $f->submit('repo_add', lang::translate('Add'));
        $f->formEnd();
        return $f->getStr();
    }

    /**
     * var holding errors
     * @var array $errors
     */
    public $errors = array();

    /**
     * validates a repo before adding to db
     * @return type
     */
    public function validateRepo() {

        $repo = html::specialDecode($_POST['repo']);
        if (!valid::url($repo)) {
            $this->errors['url'] = lang::translate('Not a correct repo URL');
            return;
        }


        $bean = db_rb::getBean('gitrepo', 'repo', $repo);
        if ($bean->id) {
            $this->errors['repo'] = lang::translate('Repo already exists');
            return;
        }

        $no_dot = str_replace('.git', '', $repo);
        $bean = db_rb::getBean('gitrepo', 'repo', $no_dot);
        if ($bean->id) {
            $this->errors['repo'] = lang::translate('Repo already exists');
            return;
        }

        $command = 'git ls-remote ' . $repo;
        exec($command, $output, $return_var);
        if ($return_var) {
            $this->errors['repo'] = lang::translate('URL does not seem to be a git repo. If you know this is a git repo, please try again.');
            return;
        }
    }

    /**
     * show form, validate, add repo
     */
    public function viewAddRepo() {
        if (isset($_POST['repo_add'])) {
            $this->validateRepo();
            if (empty($this->errors)) {
                $res = $this->dbAddRepo();
                http::locationHeader("/gitbook/checkout?id=$res", lang::translate('Will now checkout repo'));
            } else {
                echo html::getErrors($this->errors);
            }
        }
        echo $this->addForm();
    }

    /**
     * get a preo form db
     * @param int $id
     * @return array $repo
     */
    public function get($id) {
        return db_q::select('gitrepo')->filter('id =', $id)->fetchSingle();
    }

    /**
     * get repo path
     * @param int $id repo id
     * @param string $type controller or file
     * @return string
     */
    public function fileRepoPath($id, $type = 'file', $options = array()) {

        $repo = $this->get($id);
        $path = $this->repoName($repo['repo']);

        if (isset($options['docs-folder'])) {
            $path.= "/" . $options['docs-folder'];
        }

        if ($type == 'file') {
            $path = _COS_PATH . "/private/gitbook/$repo[user_id]" . "/$path";
        }
        return $path;
    }

    /**
     * 
     */
    public function viewExports($id) {

        $path = $this->fileRepoPath($id, 'file');
        $repo = $this->get($id);
        $path = config::getWebFilesPath() . $this->exportsDir($id);
        $path_full = config::getFullFilesPath() . $this->exportsDir($id);
        $name = $this->repoName($repo['repo']);
        $exports = $this->exportFormats();

        $str = lang::translate('Exports: ');

        $i = count($exports);
        foreach ($exports as $export) {

            $file = $path_full . "/$name.$export";


            if (file_exists($file)) {
                $location = $path . "/$name.$export";
                $str.= html::createLink($location, strtoupper($export));
                $str.= ' ';
            }

            $i--;
            if ($i) {
                $str.= MENU_SUB_SEPARATOR;
            }
        }
        return $str;
    }

    /**
     * globs a dir based on pattern
     * @param string $filepath
     * @param string $pattern
     * @return array $files dirs and files
     */
    public function globdir($filepath, $pattern = null) {
        $dirs = glob($filepath . "/*", GLOB_ONLYDIR);
        $files = glob($filepath . $pattern);
        $all = array_unique(array_merge($dirs, $files));
        return $all;
    }

    public function ignore($file, $options) {
        if (in_array($file, $options)) {
            return true;
        }
        return false;
    }

    /**
     * 
     * @param type $path
     * @param type $ext
     * @return type
     */
    public function getFilesAry($path, $ext, $options = array()) {

        $top = $this->globdir($path, $ext);
        $final = array();

        foreach ($top as $file) {
            if ($this->ignore($file, $options)) {
                continue;
            }

            if (is_dir($file)) {
                $files = $this->globdir($file, $ext);
                $final = array_merge($final, $files);
            } else {
                $final[] = $file;
            }
        }
        return $final;
    }

    public $allowed = array('md', 'jpg', 'gif', 'png');

    /**
     * get repo name repo url
     * @param string $url
     * @return string $name
     */
    public function repoName($url) {
        $ary = parse_url($url);
        $parts = explode('/', $ary['path']);
        $last = array_pop($parts);
        return str_replace('.git', '', $last);
    }

    /**
     * checkout or clone repo
     */
    public function checkoutAction() {
        $id = $_GET['id'];
        ?>
        <div class="progress">
            <img class ="loader_gif" style="float:left;margin:3px 5px 0 0;" src="/images/load.gif" width="16" />
            <div class="loader_message"><?= lang::translate("Wait while generating site. This may take a minute or two") ?></div>
        </div>
        <div class ="result">
        </div>
        <script type="text/javascript">


            $.ajaxSetup({
            });

            $.get("/gitbook/ajax?id=<?= $id ?>", function (data) {
                $('.loader_message').html(data);
                $('.loader_gif').hide();
            });

        </script>
        <?php
    }

    /**
     * return name of public md file with all markdown
     * @param type $id
     * @return string
     */
    public function mdAllFile($id) {
        $row = $this->get($id);
        $md_file = config::getFullFilesPath() . $this->exportsDir($id) . "/$row[name]";
        return $md_file;
    }

    /**
     * perform ajax call
     */
    public function ajaxAction() {

        $sleep = 0;
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

        if (!user::ownID('gitrepo', $id, session::getUserId())) {
            echo "You can not perform action on this repo. ";
            die();
        }

        sleep($sleep);
        echo lang::translate('Fetching repo ') . "<br />";
        $res = $this->checkoutRepo($id);
        if ($res) {
            echo lang::translate('Could not checkout repo. Somethings went wrong. Try again later') . "<br />";
            die();
        }

        sleep($sleep);

        // get parse options from git repos YAML
        $options = $this->yamlAsAry($id);
        $formats = $this->exportFormats();

        // export all md files to single file
        $md_file = $this->mdAllFile($id);
        $str = $this->filesAsStr($id);
        $write_res = file_put_contents($md_file, $str);
        if (!$write_res) {
            echo lang::translate('Could not write to file system ') . "<br />";
            return;
        }
        
        if (in_array('html', $formats)) {
            
            
            // generate HTML fragment which will be used as menu
            $this->generateHtmlMenu($id, $options);
            
            // run pandoc
            $this->pandocCommand($id, 'html', $options);
            
            // html not self-contained
            $res = $this->moveAssets($id, 'html', $options);
            if (!$res) {
                $this->errors[] = lang::translate('Could not move all HTML assets');
            }
        }

        if (in_array('epub', $formats)) {
            $this->pandocCommand($id, 'epub', $options);
        }
        
        if (in_array('mobi', $formats)) {    
            $this->kindlegenCommand($id, 'mobi', $options);
        }
        
        if (in_array('pdf', $formats)) {
            $this->pandocCommand($id, 'pdf', $options);
        }

        if (in_array('docbook', $formats)) {
            $this->pandocCommand($id, 'db', $options);
        }
        
        if (in_array('texi', $formats)) {
            $this->pandocCommand($id, 'texi', $options);
        }

        sleep($sleep);
        if (empty($this->errors)) {
            echo lang::translate("If no error were reported, you exports has been generated. ");
        } else {
            echo html::getErrors($this->errors);
        }
        die;
    }
    
    public function generateHtmlMenu($id, $options) {

        // repo name
        $repo = $this->get($id);
        $repo_name = $this->repoName($repo['repo']);
        
        $export_dir = $this->exportsDirFull($id);
        $export_file = "$export_dir/header.html";
        
        $formats = $this->exportFormats();
        //$str = '';
        $ary = array ();
        foreach ($formats as $format) {
            $ary[] = "<li>" . html::createLink("$repo_name.$format", strtoupper($format)) . "</li>";
            //$ary[] = "<a href=\"\">$format</a>";
        }
        $ary[] = "<li>" . html::createLink('/', 'Go to gittobook.org') . "</li>";
        $str = '<div id="main_menu"><ul>' . implode('', $ary) . '</ul></div>';
                
        file_put_contents($export_file, $str);

    }

    /**
     * return gitbook.ini gitbook_exports as array
     * @return type
     */
    public function exportFormats() {
        $exports = config::getModuleIni('gitbook_exports');
        return explode(",", $exports);
    }

    /**
     * moves assets for html
     * @param type $id
     * @param type $type
     * @param type $options
     * @return boolean
     */
    public function moveAssets($id, $type, $options) {
        $repo = $this->get($id);

        // move to dir
        $repo_path = $this->repoPath($repo);
        $export_path = config::getFullFilesPath() . $this->exportsDir($id);

        $css_path = $repo_path . "/css";
        if (file_exists($css_path)) {
            $css_files = $this->globdir($css_path, "/*");
            $res = $this->checkLegalAssets($css_files, 'css');

            if (!$res) {
                return false;
            }
            $fs = new Filesystem();
            $fs->mirror($css_path, $export_path . "/css", null, array('delete' => true));
        }

        $image_path = $repo_path . "/images";
        if (file_exists($image_path)) {
            $image_files = $this->globdir($image_path, "/*");
            $res = $this->checkLegalAssets($image_files, 'images');

            if (!$res) {
                return false;
            }
            $fs = new Filesystem();
            $fs->mirror($image_path, $export_path . "/images", null, array('delete' => true));
        }
        return true;
    }

    /**
     * return boolean based on $files given. If one does not match return false
     * @param array $files
     * @return boolean $res 
     */
    public function checkLegalAssets($files, $dir) {
        $legal = explode(",", config::getModuleIni('gitbook_allow_assets'));
        foreach ($files as $file) {
            $file_base = basename($file);
            $ext = file::getExtension($file_base);
            if (!in_array($ext, $legal)) {
                $illegal = "$dir/$file_base";
                $this->errors[] = lang::translate('You have a file in your css path which is not allowed. Found file: ') . $illegal;
                $this->errors[] = lang::translate("Remove it from your repo with: git rm -f ") . $illegal;
                return false;
            }
        }
        return true;
    }

    /**
     * get complete repo path from checkout path and repo name
     * @param array $repo
     * @return string $path repo path
     */
    public function repoPath($repo) {
        $checkout = $this->checkoutPath($repo);
        return $checkout . "/$repo[name]";
    }

    /**
     * some default options when meta.yaml is not supplied.
     * @return type
     */
    public function defaultOptions() {
        $str = <<<EOF
---
title: Git To Book
subject: Test
author: Test
keywords: ebooks, pandoc, pdf, html, epub, mobi
rights: Creative Commons Non-Commercial Share Alike 3.0
language: en-US
format-arguments:
    pdf: -s -S --latex-engine=pdflatex --number-sections --toc
    html: -s -S --chapters --number-sections --toc -t html5
    epub: -s -S  --epub-chapter-level=3 --number-sections --toc --epub-chapter-level=4
...
EOF;
        $yaml = new Parser();
        return $yaml->parse($str);
    }

    /**
     * returns parsed yaml
     * @param type $id
     * @return type
     */
    public function yamlAsAry($id) {
        $yaml = new Parser();
        $file = $this->fileRepoPath($id, 'file') . "/meta.yaml";
        if (file_exists($file)) {
            $values = $yaml->parse(file_get_contents($file));
        } else {
            $values = $this->defaultOptions();
        }
        return $values;
    }

    /**
     * returns dir where exports will be put
     * @param type $id
     * @return type
     */
    public function exportsDir($id) {
        $exports_dir = "/exports/$id";
        file::mkdir($exports_dir);
        return $exports_dir;
    }

    /**
     * gets all md files as a single string
     * @param type $id
     * @return string|boolean
     */
    public function filesAsStr($id, $options = array('encode' => true)) {

        $yaml = $this->yamlAsAry($id);
        $repo_path = $this->fileRepoPath($id, 'file', $yaml);

        $files = $this->getFilesAry($repo_path, "/*.md", $yaml);
        if (empty($files)) {
            return false;
        }
        $files_str = '';
        foreach ($files as $file) {
            $files_str.= file_get_contents($file) . "\n";
        }
        return html::specialEncode($files_str);
    }

    public function cleanOptions($flags) {
        $ary = explode(' ', $flags);
    }

    /**
     * get a parse option. From meta.yaml format-arguments or we use base options
     * @param array $options
     * @return string
     */
    public function pandocArgs($id, $type, $options) {
        $key = 'format-arguments';
        if (isset($options[$key])) {
            $o = $options[$key];
            if (isset($o[$type])) {
                
                $ok = $this->pandocValidate($o[$type], $type);
                if (!$ok) {
                    return false;
                }
                $o[$type].= $this->pandocAddArgs($id, $type);
                return $o[$type];
            }
        }
        return "-s -S --chapters --self-contained --number-sections --toc ";
    }
    
    /**
     * adds some default arguments to pandoc build command
     * @param string $id repo id
     * @param string $type e.g. html or pdf
     * @return string $str modified string
     */
    public function pandocAddArgs ($id, $type) {
        if ($type == 'html') {
            
            // add menu with downloads in html header
            $export_dir = $this->exportsDirFull($id);
            $export_file = "$export_dir/header.html";
            $str = " --include-before-body=$export_file ";
            
            return $str;
        }
        
        if ($type == 'pdf') {
            return " --latex-engine=xelatex ";
        }
    }
    
    /**
     * validate option string
     * @param string $str
     */
    public function pandocValidate($str, $type) {

        // parse commandline options with php 
        // command line options usaually start with - and --
        //$str = "-s -S --cchapters=7 -V geometry:margin=1in -V documentclass=memoir -V lang=danish";

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
            'template' => null,
            // Use the specified CSS file to style the EPUB
            'epub-stylesheet' => null,
            'epub-chapter-level' => '1-6',
            // epub-embed-font
            'epub-embed-font' => null,
            'V' => array(
                'geometry:margin',
                'documentclass', 
                'lang',
                'fontsize',
                'mainfont',
                'sansfont',
                'monofont',
                'boldfont',
                'version'),
        );

        $o = new optValid();
        $ary = $o->split($str);
        $ary = $o->getAry($ary);
        $ary = $o->setSubVal($ary);
        $ok = $o->isValid($ary, $allow);
        
        if (!$ok) {
            $this->pandocArgErrors($o->errors, $type);
            return false;
        } else {
            return true;
        }
        
    }
    
    public function pandocArgErrors ($errors, $type) {
        
        foreach($errors as $error) {
            $str = lang::translate("Found illigal options in 'format-arguments': ");
            $str.= "'$error' ";
            $str.= lang::translate("in type ") . "'$type'. ";
            $str.= lang::translate('Remove it from meta.yaml');
            $this->errors[] = $str;
            
        }
    }
    

    /**
     * run kindlegen
     * @param int $id repo id
     */
    public function kindlegenCommand($id, $type, $options) {
        exec("kindlegen", $output, $ret);
        if ($ret) {
            log::error('Kindlegen was not found on system');
            die();
        }
        if (!isset($options['cover-image'])) {
            $e = lang::translate('Epub fails! Mobi has no cover image. Specify this in a meta.yaml file');
            $this->errors[] = $e;
            return false;
        }

        $repo = $this->get($id);
        $export_dir = $this->exportsDir($id);
        $export_dir = config::getFullFilesPath() . "$export_dir";

        // command
        $command = "cd $export_dir && kindlegen " .
                $repo['name'] . ".epub" .
                " -o " .
                $repo['name'] . ".mobi";

        exec($command, $output, $ret);
        if ($ret) {
            log::error($command);
            echo "Kindle failed<br />";
        }
        echo lang::translate('Done ') . "Mobi<br/>";
    }

    public function exportsDirFull ($id) {
        $export_dir = $this->exportsDir($id);
        $export_dir_full = config::getFullFilesPath() . $export_dir;
        return $export_dir_full;
    }
    /**
     * 
     * @param repo $id
     * @param string $type pdf, mobi, epub, etc. 
     * @param array $options
     */
    public function pandocCommand($id, $type, $options = array()) {

        // repo name
        $repo = $this->get($id);
        $repo_name = $this->repoName($repo['repo']);

        // get export file name and create dirs
        $export_file = $this->exportsDirFull($id) . "/$repo_name.$type";

        // get repo path
        $repo_path = $this->fileRepoPath($id, 'file');

        // begin command
        $command = "cd $repo_path && ";

        // add base flags
        $base_flags = $this->pandocArgs($id, $type, $options);
        if (!$base_flags) {
            echo html::getErrors($this->errors);
            die;
        }

        $command.= "pandoc $base_flags ";
        $command.= "-o $export_file ";
        $title_file = $repo_path . "/meta.yaml";
        if (file_exists($title_file)) {
            $command.= $title_file . " ";
        }

        $files_str = $this->mdAllFile($id);
        if ($files_str === false) {
            echo lang::translate("Error. You will need to have .md files written in markdown in your repo. No such files found!");
            die();
        }

        $command.=$files_str . " 2>&1";
        $output = array();
        exec($command, $output, $ret);
        if ($ret) {
            echo lang::translate("Failed to create export of type: ") . "$type" . "<br />";
            echo html::getErrors($output);
            log::error($command);
            log::error($output);
            die();
        }
        echo lang::translate("Done ") . $type . "<br/>";
    }

    /**
     * simple check to see if a path is a git repo
     * @param type $repo_path
     * @return boolean
     */
    public function isRepo($row) {
        $repo_path = $this->checkoutPath($row['repo']);
        $repo = $repo_path . "/$row[name]/.git";
        if (file_exists($repo)) {
            return true;
        }
        return false;
    }

    /**
     * checkout repo - clone if it does not exists
     * @param int $id repo id
     * @return int $res return value from shell command
     */
    public function checkoutRepo($id) {

        $row = db_q::select('gitrepo')->filter('id =', $id)->fetchSingle();
        if (!$this->isRepo($row)) {
            $res = $this->clone_($row);
        } else {
            $res = $this->checkout($row);
        }
        return $res;
    }

    /**
     * clone a repo to file system
     * @param type $row
     * @return type
     */
    public function clone_($row) {
        $clone_path = $this->checkoutPath($row['repo']);
        $command = "cd $clone_path && git clone $row[repo]";
        exec($command, $output, $res);
        if ($res) {
            log::error($output);
        }
        return $res;
    }

    /**
     * checkout a repo to master 
     * @param array $row db row
     * @return int $res result of exec
     */
    public function checkout($row) {
        $checkout_path = $this->checkoutPath($row['repo']);
        $checkout_path.= "/$row[name]";
        $command = "cd $checkout_path && git pull";
        exec($command, $output, $res);
        if ($res) {
            log::error($output);
        }
        return $res;
    }

    /**
     * get repo file path from repo name
     * @param string $repo
     * @return string $path
     */
    public function checkoutPath($repo) {

        $path = _COS_PATH . "/private/gitbook/" . session::getUserId();
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }
    
    
    
}
