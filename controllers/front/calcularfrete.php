<?php
/**
 * Módulo de fretes usando a API do FreteClick
 *  @author    Marcelo Almeida (contato@marceloalmeida.dev)
 *  @copyright 2010-2020 FreteClick
 */

class FreteclickCalcularfreteModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        echo $this->module->quote(json_decode(file_get_contents('php://input'), false));
		exit;
    }
}
