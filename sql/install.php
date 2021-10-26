<?php
/**
 *  @author    Marcelo Almeida (contato@marceloalmeida.dev)
 *  @copyright 2010-2021 Frete Click
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0) 
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
