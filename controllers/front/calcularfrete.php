<?php
/**
 *  @author    Marcelo Almeida (contato@marceloalmeida.dev)
 *  @copyright 2010-2021 Frete Click
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0) 
 */

class FreteclickCalcularfreteModuleFrontController extends ModuleFrontController
{
  public function initContent()
  {
    echo $this->module->quote(json_decode(Tools::file_get_contents('php://input'), false));
    exit;
  }
}
