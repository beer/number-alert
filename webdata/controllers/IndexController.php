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
        if (!$_GET['id']) {
            $_GET['id'] = 'ptt:Japan_Travel';
        }
        $k_value = intval($_GET['k']) ?: 10;
        if (!$set = NumberSet::getSet(strval($_GET['id']), false)) {
            return $this->json("找不到 {$_GET['id']}");
        }
        $this->view->set = $set;
        $this->view->cluster_set = PatternChecker::getClusteredPatterns($set, $k_value);
    }
}

