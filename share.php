<?php



class gittobook_share {

    public function getShareString($title, $description) {
        $url = conf::getSchemeWithServerName();
        $url.= $_SERVER['REQUEST_URI'];

        $email_description = $description . "\n\n$url"; 
        
        $url = rawurlencode($url);
        $title = rawurlencode($title);
        $email_description = rawurlencode($email_description); 
        
        $description = rawurlencode($description);
        if (empty($description)) {
            $description = $title;
        }
                

        
        $str = <<<EOF
<a href="mailto:?subject={$title}&amp;body={$email_description}">email</a>, 
<a href="https://plus.google.com/share?url={$url}">Google+</a>,
<a href="http://twitter.com/share?url={$url}&amp;text={$description}">Twitter</a>,
<a href="http://www.facebook.com/sharer.php?u={$url}&t={$description}">Facebook</a>.
EOF;
        return $str;
    }
}
