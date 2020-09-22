# SDK

SDK para a API da plataforma Frete Click
https://api.freteclick.com.br


# Instalação
composer require freteclick/sdk

#Uso

<pre>
 use freteclick\SDK;

 $origin = new freteclick\SDK\Models\Origin(); 
 $destination = new freteclick\SDK\Models\Destination();

 $SDK = new SDK($api_key);
 $cotafacil = $SDK->cotaFacilClient();
 $result = $cotafacil->quote($origin,$destination);
 
 print_r($result); 
</pre>
