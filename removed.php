diff --git a/module.php b/module.php
index 9c62ff1..a15bff6 100644
--- a/module.php
+++ b/module.php
@@ -18,7 +18,6 @@ use diversen\pagination;
 use diversen\sendfile;
 use diversen\session;
 use diversen\strings;
-use diversen\template;
 use diversen\template\assets;
 use diversen\template\meta;
 use diversen\uri\direct;
@@ -28,6 +27,7 @@ use Symfony\Component\Filesystem\Filesystem;
 use Symfony\Component\Yaml\Dumper;
 use Symfony\Component\Yaml\Parser;
 use diversen\uri;
+use diversen\mirrorPath;
 
 use modules\gittobook\share as share;
 use modules\count\module as counter;
@@ -983,18 +983,11 @@ class module {
         $repo_path = $this->repoPath($id);
         $export_path = $this->exportsDir($id);
 
-        $image_path = $repo_path . "/images";
-        if (file_exists($image_path)) {
-            $image_files = $this->globdir($image_path, "/*");
-            $res = $this->checkLegalAssets($image_files, 'images');
+        $m = new mirrorPath();
+        $m->deleteBefore = false;
+        $m->allowTypes = ['jpg','png', 'gif', 'jpeg'];
+        $m->mirror($repo_path, $export_path );
 
-            if (!$res) {
-                echo html::getErrors($this->errors);
-                return false;
-            }
-            $fs = new Filesystem();
-            $fs->mirror($image_path, $export_path . "/images", null, array('delete' => true));
-        }
         return true;
     }
 
