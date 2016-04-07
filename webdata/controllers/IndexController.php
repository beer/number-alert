<?php

class IndexController extends Pix_Controller
{
    public function indexAction()
    {
    }

    public function getjsonAction()
    {
    }

    public function clusterAction()
    {
        if (!$_GET['set']) {
            $_GET['set'] = 'ptt:Japan_Travel';
        }
        $k_value = intval($_GET['k']) ?: 5;
        if (!$set = NumberSet::getSet(strval($_GET['set']), false)) {
            return $this->json("找不到 {$_GET['set']}");
        }
        $this->view->set = $set;
        $this->view->cluster_set = PatternChecker::getClusteredPatterns($set, $k_value);
    }
}

