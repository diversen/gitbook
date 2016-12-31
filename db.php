<?php

namespace modules\gittobook;

use diversen\db\rb;
use diversen\html;
use diversen\date;
use diversen\git;
use diversen\session;
use diversen\db\q;

class db {
        /**
     * add repo to db
     * @return type
     */
    public function addRepo() {
        $gitrepo = html::specialDecode($_POST['repo']);
        $bean = rb::getBean('gitrepo', 'repo', $gitrepo);
        $bean->uniqid = md5(uniqid('', true));
        $bean->name = git::getRepoName($gitrepo);
        $bean->repo = $gitrepo;
        $bean->date = date::getDateNow(array('hms' => true));
        $bean->user_id = session::getUserId();
        $bean->published = 1;
        return rb::commitBean($bean);
    }
    
    /**
     * delete repo row from id
     * @param int $id
     * @return int $res
     */
    public function deleteRepo($id) {
        return $res = q::delete('gitrepo')->filter('id =', $id)->exec();
    }
    
    /**
     * update repo row
     * @param int $id
     * @param array $values
     * @return int $res
     */
    public function updateRepo ($id, $values) {
        return rb::updateBean('gitrepo', $id, $values);
    } 
}
