<?php


$vendor = dirname(__FILE__) . "/vendor";
require "$vendor/autoload.php";

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Filesystem\Filesystem;
use diversen\valid;
use diversen\db\rb as db_rb;
use diversen\html;
use diversen\html\helpers as html_helpers;
use diversen\cli\optValid;
//use diversen\pagination;
//use diversen\buffer;
use diversen\uri\direct;
use Gregwar\Image\Image;
//use diversen\file\string as file_string;

class gitbook {

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
    
    /**
     * will generate a cover image for epub and mobi files if it has not 
     * it also rewrite yaml
     * be supplied
     */
    public function coverGenerate ($id, $yaml) {
        $save = _COS_HTDOCS . "/books/$id/cover.jpg";

        $font = _COS_HTDOCS . "/fonts/captcha.ttf";
        $image = config::getModulePath('gitbook') . "/images/cover.jpg";
        
        // ration 600 x 800
        $title = mb_substr($yaml['title'], 0 , 25);
        
        
        $img = Image::open($image);
            //->font title       left top fs   angel
        $img->write($font, $title, 20, 500, 80, 0, '#0066cc', 'left');
        $written_by = lang::translate('Written by: ');
        $img->write($font, $written_by, 20, 1000, 40, 0, '#333', 'left');
        
        
        $img->save($save);
        
        $yaml['cover-image'] = $save;
        return $yaml;
    }
    
    public function coverScale ($id) {
        
    }
    
    /**
     * connect to database
     */
    public function __construct() {
        db_rb::connect();
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
     * display all user repos
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
            echo $this->viewRepos($rows);
        }

        echo $this->viewAddRepo();
    }
    
    
    /**
     * list actions public
     */
    public function indexAction() {
        
        $per_page = 20;
        $num_rows = db_q::numRows('gitrepo')->fetch();
        
        $rows = db_q::select('gitrepo')->
                order('hits', 'DESC')->
                limit(0, 1)->
                fetch();
        
        
        echo $this->viewRepos($rows);
    }

    /**
     * view repo rows
     * @param array $rows
     * @return string $str HTML
     */
    public function viewRepos($rows) {

        $str = '';
        foreach ($rows as $row) {
            $str.= $this->viewRepo($row);
        }
        return $str;
    }
    
    /**
     * view single repo
     * @param array $row
     * @return string $str HTML
     */
    public function viewRepo($row) {
        $row = html::specialEncode($row);
        $str = '';
        
        $str.=$this->viewHeaderCommon($row);
        
        $str.= $this->optionsRepo($row);
        $str.= MENU_SUB_SEPARATOR_SEC;

        $ary = $this->exportsArray($row['id']);
        $str.= lang::translate('Exports: ');
        $str.= implode(MENU_SUB_SEPARATOR, $ary);
        $str.= "<hr />";
        return $str;
    }

    /**
     * display repo options
     * @param array $row
     * @return string $str
     */
    public function optionsRepo($row) {

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

        if (isset($_POST['delete_files'])) {
            $this->deletePublicFiles($_GET['id']);
            $this->repoDeleteFiles($_GET['id']);
            $this->updateRow($_GET['id'], array('published' => 0));
            http::locationHeader('/gitbook/repos', lang::translate('Repo files has been purged!'));
        }
        
        if (isset($_POST['delete_all'])) {
            $this->deletePublicFiles($_GET['id']);
            $this->repoDeleteFiles($_GET['id']);
            $this->deleteRow($_GET['id']);
            http::locationHeader('/gitbook/repos', lang::translate('Repo files has been purged. Database entry has been removed!'));
        }
        
        echo html_helpers::confirmDeleteForm(
                'delete_files', lang::translate('Remove git repo and exported files - but leave repo in database'));
        
        echo html_helpers::confirmDeleteForm(
                'delete_all', lang::translate('Remove everything. Be carefull as any links to this repo no longer will be found!'));
    }

    /**
     * delete public files
     * @param int $id repo id
     */
    public function deletePublicFiles ($id) {
        $public_path = $this->exportsDir($id);
        file::rrmdir($public_path);
    }
    
    /**
     * delete repo files from id
     * @param int $id
     */
    public function repoDeleteFiles ($id) {
        $private_path = $this->repoPath($id);
        file::rrmdir($private_path);        
    }
    
    /**
     * update repo row
     * @param int $id
     * @param array $values
     * @return int $res
     */
    public function updateRow ($id, $values) {
        return db_rb::updateBean('gitrepo', $id, $values);
    } 

    /**
     * delete repo row from id
     * @param int $id
     * @return int $res
     */
    public function deleteRow($id) {
        return $res = db_q::delete('gitrepo')->filter('id =', $id)->exec();
    }

    /**
     * form for adding the repo
     * @return string $str html
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
        
        // without .git
        $no_dot = str_replace('.git', '', $repo);
        $bean = db_rb::getBean('gitrepo', 'repo', $no_dot);
        if ($bean->id) {
            $this->errors['repo'] = lang::translate('Repo already exists');
            return;
        }
        
        // with .git
        $bean = db_rb::getBean('gitrepo', 'repo', $no_dot . ".git");
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
    public function get($var) {
        if (!is_array($var)) {
            return db_q::select('gitrepo')->filter('id =', $var)->fetchSingle();
        }
        return db_q::select('gitrepo')->filterArray($var, 'AND')->fetchSingle();
    }

    /**
     * get repo path
     * @param int $id repo id
     * @param string $type controller or file
     * @return string
     */
    public function repoPath($id) {

        $repo = $this->get($id);
        $path = $this->repoName($repo['repo']);

        //if ($type == 'file') {
            $path = _COS_PATH . "/private/gitbook/$id" . "/$path";
        //}
        return $path;
    }
    
    /**
     * return a books export dir
     * @param int $id
     * @return string $path
     */
    public function exportsDirWeb ($id) {
        return "/books/$id";
    }
    
    /**
     * return a books export dir with repo name
     * /books/10/a-book-name
     * @param type $row
     * @return type
     */
    public function exportsDirBook($row) {
        
        return $this->exportsDirWeb($row['id']) . "/" . strings::utf8SlugString($row['name']);
    }

    /**
     * get array of export files with type as key and file_path as value
     * @param int $id repo id
     * @return array $ary array with type as key and file_path as value
     */
    public function exportsArray($id, $options = array()) {

        $repo = $this->get($id);
        $path = $this->exportsDirWeb($id);
        $path_full = $this->exportsDir($id);
        $name = $this->repoName($repo['repo']);
        $exports = $this->exportFormatsIni();

        $ary = array ();
        foreach ($exports as $export) {
            $file = $path_full . "/$name.$export";

            if (file_exists($file)) {
                $location = $path . "/$name.$export";
                
                if (!isset($options['path'])) {
                    $ary[$export]= html::createLink($location, strtoupper($export));
                } else {
                    $ary[$export] = $location;
                }
                
            }
        }
        return $ary;
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
        $md_file = $this->exportsDir($id) . "/$row[name].md";
        return $md_file;
    }

    /**
     * perform ajax call
     */
    public function ajaxAction() {

        $sleep = 0;
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!user::ownID('gitrepo', $id, session::getUserId())) {
            echo lang::translate("You can not perform any action on this page.");
            die();
        }

        sleep($sleep);
        echo lang::translate('Fetching repo ') . "<br />";
        $res = $this->execCheckout($id);
        if ($res) {
            echo lang::translate('Could not checkout repo. Somethings went wrong. Try again later') . "<br />";
            die();
        }

        sleep($sleep);
        
        // remove old builds
        $public_path = $this->exportsDir($id);
        file::rrmdir($public_path);
        
        //$cover_res = $this->coverGenerate($id);
        $yaml = $this->yamlAsAry($id);
        $cover_default = config::getModulePath('gitbook') . "/images/cover.jpg";
        
        // There has not been set a cover
        if ($yaml['cover-image'] == $cover_default) {
            $yaml = $this->coverGenerate($id, $yaml);
        }
        
        // generate yaml meta in exports
        $yaml_res = $this->yamlExportsMeta($id, $yaml);
        if (!$yaml_res) {
            echo html::getError('Could not write to filesystem. If you are admin you should fix this.');
        }
        
        
        
        // create a single file with yaml and markdown
        $md_file = $this->mdAllFile($id);
        $str = $this->filesAsStr($id);
        $write_res = file_put_contents($md_file, $str);
        if (!$write_res) {
            echo lang::translate('Could not write to file system ') . "<br />";
            die();
        }
        
        // get parse options from git repos YAML
        $options = $this->yamlAsAry($id);
        
        $bean = db_rb::getBean('gitrepo', 'id', $id);
        $bean->subtitle = $options['Subtitle'];
        $bean->title = $options['title'];
        R::store($bean);

        // get export formats
        $formats = $this->exportFormatsReal($options['format-arguments']);

        
        
        $exports = array ();
        
        // epub
        if (in_array('epub', $formats)) {
            
            
            
            $epub_ok = $this->pandocCommand($id, 'epub', $options);
            if (!$epub_ok) {
                $exports[] = 'epub';
            }
        }
        
        // mobi
        if (in_array('mobi', $formats)) {    
            $mobi_ok = $this->kindlegenCommand($id, 'mobi', $options);
            if (!$mobi_ok) {
                $exports[] = 'mobi';
            }
        }
        
        // pdf
        if (in_array('pdf', $formats)) {
            $pdf_ok = $this->pandocCommand($id, 'pdf', $options);
            if (!$pdf_ok) {
                $exports[] = 'pdf';
            }
        }

        // docbook
        if (in_array('docbook', $formats)) {
            $docbook_ok = $this->pandocCommand($id, 'docbook', $options);
            if (!$docbook_ok) {
                $exports[] = 'docbook';
            }
        }
        
        // texi
        if (in_array('texi', $formats)) {
            $texi_ok = $this->pandocCommand($id, 'texi', $options);
            if (!$texi_ok) {
                $exports[] = 'texi';
            }
        }
        
        // html
        if (in_array('html', $formats)) {
                
            // generate HTML fragment which will be used as menu
            $this->exportsHtmlMenu($id, $exports);
            
            // run pandoc
            $this->pandocCommand($id, 'html', $options);
            
            // html not self-contained
            $res = $this->moveAssets($id, 'html', $options);
            if (!$res) {
                $this->errors[] = lang::translate('Could not move all HTML assets');
            } else {
                $this->updateRow($id, array('published' => 1));
            }
        }
        

        sleep($sleep);
        if (empty($this->errors)) {
            echo lang::translate("If no error were reported, you exports has been generated. ");
        } else {
            echo html::getErrors($this->errors);
        }
        die;
    }
    
    /**
     * generate a header for html exports
     * @param int $id repo id
     */
    public function exportsHtmlMenu($id) {

        $export_dir = $this->exportsDirFull($id);
        $export_file = "$export_dir/header.html";

        $ary = array ();
        $exports = $this->exportsArray($id);
        foreach ($exports as $format) {
            $ary[] = "<li>" . $format . "</li>";
        }
        $ary[] = "<li>" . html::createLink('/', 'Go to gittobook.org') . "</li>";
        $str = '<div id="main_menu"><ul>' . implode('', $ary) . '</ul></div>';                
        file_put_contents($export_file, $str);

    }

    /**
     * return gitbook.ini gitbook_exports as array
     * @return array $ary exports from ini
     */
    public function exportFormatsIni() {
        $exports = config::getModuleIni('gitbook_exports');
        return explode(",", $exports);
    }
    
    /**
     * get export formats real. Formats which is both in ini settings and 
     * format-arguments
     * @param array $options format-arguments
     * @return array $ary formats
     */
    public function exportFormatsReal ($options) {
        $ini = $this->exportFormatsIni();
        $ary = array ();
        foreach($options as $key => $val) {
            if (in_array($key, $ini)) {
                $ary[] = $key;
            }
        }
        return $ary;
    }

    /**
     * moves assets for html
     * @param int $id
     * @param string $type
     * @param array $options
     * @return boolean
     */
    public function moveAssets($id, $type, $options) {
        $repo = $this->get($id);

        // move to dir
        $repo_path = $this->repoPath($id);
        $export_path = $this->exportsDir($id);

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
     * @param string $dir
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
     * get yaml default string
     * @return string $str
     */
    public function yamlDefaultStr () {
        $date = date::getDateNow();
        $cover = config::getModulePath('gitbook') . "/images/cover.jpg";
        $template = config::getModulePath('gitbook') . "/templates/body.html";
        $str = <<<EOF
---
title: Untitled
Subtitle: Author has not added a subtitle yet
subject: Not known
author:
- John Doe
- And others
keywords: ebooks, pandoc, pdf, html, epub, mobi
rights: Creative Commons Non-Commercial Share Alike 3.0
language: en-US
cover-image: {$cover}
date: '{$date}'
# default formats
format-arguments:
    pdf: --toc
    html: -s -S --template={$template} --chapters --number-sections --toc
    epub: -s -S  --epub-chapter-level=3 --number-sections --toc
    mobi:
...
EOF;
        return $str;
    }
                
    /**
     * some default options when meta.yaml is not supplied.
     * @return string $str defauly yaml options
     */
    public function yamlDefaultAry() {
        $str = $this->yamlDefaultStr();
        $yaml = new Parser();
        return $yaml->parse($str);
    }

    /**
     * returns parsed yaml
     * @param int $id repo id
     * @return array $values yaml as array
     */
    public function yamlAsAry($id) {
        
        $yaml = new Parser();
        $file = $this->repoPath($id) . "/meta.yaml";
        
        if (file_exists($file)) {
            $values = $yaml->parse(file_get_contents($file));
            $values = $this->yamlFix($values);
        } else {
            $values = $this->yamlDefaultAry();
        }
        return $values;
    }
    
    /**
     * if any of the values is missing in meta.yaml
     * we insert default values
     * @param type $values
     * @return type
     */
    public function yamlFix ($values) {
        $default = $this->yamlDefaultAry();
        foreach ($default as $key => $val) {
            if (isset($values[$key])) {
                $default[$key] = $values[$key];
            }
        }
        return $default;
    }

    /**
     * returns dir where exports will be put
     * @param type $id
     * @return type
     */
    public function exportsDir($id) {
        if (!$id) {
            die('exportsDir() function should always get and ID ');
        }
        $exports_dir = _COS_HTDOCS . "/books/$id";
        file::mkdirDirect($exports_dir);
        return $exports_dir;
    }

    /**
     * gets all md files as a single string
     * @param type $id
     * @return string|boolean
     */
    public function filesAsStr($id) {

        $repo_path = $this->repoPath($id);        
        $files = $this->getFilesAry($repo_path, "/*.md");
        if (empty($files)) {
            return false;
        }

        $files_str = '';
        $yaml_file = $this->exportsDir($id) . "/meta.yaml";
        if (file_exists($yaml_file)) {
            $files_str.= file_get_contents($yaml_file) . "\n\n";
        }
        
        foreach ($files as $file) {
            $files_str.= file_get_contents($file) . "\n";
        }
        return $files_str;
    }
    
    /**
     * generates a yaml file and place it in export dir
     * @param int $id repo id
     * @return boolean $res 
     */
    public function yamlExportsMeta ($id, $yaml) {
        //$yaml = $this->yamlAsAry($id);
        $dumper = new Dumper();
        $str = $dumper->dump($yaml, 2);        
        $str = "---\n" . $str . "...\n\n\n";
        $yaml_file = $this->exportsDir($id) . "/meta.yaml";
        return file_put_contents($yaml_file, $str);
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
                //$o[$type] = 
                $o[$type].= $this->pandocAddArgs($id, $type);
                return $o[$type];//$o[$type];
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
        $str ='';
        if ($type == 'html') {
            
            // add menu with downloads in html header
            // $export_dir = $this->exportsDirFull($id);
            // $export_file = "$export_dir/header.html";
            // $str.= " --include-before-body=$export_file -t html5 ";
            $str.= " -t html5 ";
        }
        
        if ($type == 'docbook') {
            $str.= " -t docbook ";
        }
        
        if ($type == 'pdf') {
            $str.= " --latex-engine=xelatex ";
        }
        
        $str.= " --from=markdown-raw_html ";
        return $str;
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
            // Specify output format.

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
            $str = lang::translate('Found illigal options in <span class="notranslate"><b>format-arguments</b></span>: ');
            $str.= "'$error' ";
            $str.= lang::translate("in type ") . "'$type'. ";
            $str.= lang::translate('Remove it from <b>meta.yaml</b>');
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
            return $ret;
    
        }

        $repo = $this->get($id);
        $export_dir = $this->exportsDir($id);

        // command
        $command = "cd $export_dir && kindlegen " .
                $repo['name'] . ".epub" .
                " -o " .
                $repo['name'] . ".mobi";

        exec($command, $output, $ret);
        if ($ret) {
            $error = lang::translate('You will need to have a title and a cover image when creating MOBI files from Epub files');
            echo html::getError($error);
            log::error($command);
            return $ret;
        }
        echo lang::translate('Done ') . "Mobi<br/>";
        return $ret;
    }

    public function exportsDirFull ($id) {
        $export_dir = $this->exportsDir($id);
        $export_dir_full = $export_dir;
        return $export_dir_full;
    }
    /**
     * runs a pandoc command based on repo id 'type' ,e.g. epub, and options
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
        $repo_path = $this->repoPath($id);

        // begin command
        $command = "cd $repo_path && ";

        // add base flags
        $base_flags = $this->pandocArgs($id, $type, $options);
        $base_flags = escapeshellcmd($base_flags);
        if (!$base_flags) {
            echo html::getErrors($this->errors);
            die;
        }

        $command.= "pandoc $base_flags ";
        $command.= "-o $export_file ";
        
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
        } else {
            echo lang::translate("Done ") . $type . "<br/>";
        }
        return $ret;
    }

    /**
     * simple check to see if a path is a git repo
     * @param type $repo_path
     * @return boolean
     */
    public function isRepo($row) {
        $repo_path = $this->repoCheckoutPath($row);
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
    public function execCheckout($id) {

        $row = db_q::select('gitrepo')->filter('id =', $id)->fetchSingle();
        if (!$this->isRepo($row)) {
            $res = $this->execClone($row);
        } else {
            $res = $this->checkout($row);
        }
        
        // if exec ok - we add a title from meta.yaml to database 
        // if one if found - else we set title to unititled
        if (!$res) {
            //$this->repoPath($repo)
        }
        
        return $res;
    }

    /**
     * clone a repo to file system
     * @param array $row
     * @return int $res
     */
    public function execClone($row) {
        $clone_path = $this->repoCheckoutPath($row);
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
        $checkout_path = $this->repoCheckoutPath($row);
        $checkout_path.= "/$row[name]";
        $command = "cd $checkout_path && git pull";
        exec($command, $output, $res);
        if ($res) {
            log::error($output);
        }
        return $res;
    }

    /**
     * get place where we checkout repo
     * @param string $repo
     * @return string $path
     */
    public function repoCheckoutPath($repo) {

        $path = _COS_PATH . "/private/gitbook/" . $repo['id'];
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }  
    
    /**
     * books action
     * @return boolean
     */
    public function booksAction () {
        
        // get repo id
        $id = direct::fragment(1);
        $repo = $this->get(array('published =' => 1, 'id =' => $id));
        if (empty($repo)) {
            moduleloader::setStatus(404);
            return false;
        }
        
        // check correct url
        $canon = $this->exportsDirBook($repo);
        http::permMovedHeader($canon);
        
        // increment
        $c = new count_module();
        $c->increment('gitrepo', 'hits', $id);
        
        
        
        // set meta info
        $yaml = $this->yamlAsAry($id);
        
        if (isset($yaml['language'])) {
            config::setMainIni('lang', $yaml['language']);
            // lang::loadLanguage('zh');
        }
        
        $repo = html::specialEncode($repo);
        echo $this->viewHeaderCommon($repo);
        
        
        $s = new gitbook_share();
        echo $s->getShareString($repo['title'], $repo['subtitle']);
        
        //echo $s->get('stackoverflow', $share_opt);
        
        
        //print_r($yaml['author']);
        
        $this->setMeta($yaml);
        template::setTitle($yaml['title']);
        
        // get html fragment
        $exports = $this->exportsArray($id, array ('path' => true));
        $path = _COS_HTDOCS . "/$exports[html]";
        echo file_get_contents($path);

    }
    
    public function viewHeaderCommon ($repo) {
        
        $url = $this->exportsDirWeb($repo['id']) . "/" . $repo['name'];
        $str = '';
        $str.= html::createLink($url, html::getHeadline($repo['title']));
        $str.= $repo['subtitle'] . "<br />";
        $str.= lang::translate('Repo URL: '); 
        $str.= html::createLink($repo['repo'], $repo['repo']) . "<br />";
       
        $p = new userinfo_module();
        $str.= lang::translate('Edited by: ');
        $str.= $p->getLink($repo['user_id']) . "<br />";
        return $str;
    }
    
    public function setMeta ($ary) {
        template_meta::setMeta(
                array ('description' => $ary['Subtitle'],
                       'keywords' => 'test'. $ary['keywords']));
    }
}

        

class gitbook_module extends gitbook{}