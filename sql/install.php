<?php
/**
* 2007-2020 PrestaShop
*/
$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'freteclick` (
    `id_freteclick` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_freteclick`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
