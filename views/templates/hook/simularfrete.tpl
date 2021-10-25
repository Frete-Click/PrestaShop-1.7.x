<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">FRETE CLICK</div>
    <div id="url_shipping_quote" class="panel-body" data-action="{$url_shipping_quote|escape:'htmlall':'UTF-8'}">
        <input required
          type       ="text"
          id         ="fk-cep"
          name       ="cep-destination"
          value      ="{$cep|escape:'htmlall':'UTF-8'}"
          onkeypress ="maskCep(this, '#####-###')"
          maxlength  ="9"
          class      ="form-control"
          placeholder="Informe CEP de destino"
        >

        <input type="hidden" id="product-type"           name="product-type"               value="{$product->category|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-total-price"    name="product-total-price"        value="{$product->price|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" id="product-package-qtd"    name="product-package[0][qtd]"    value="1"/>
        <input type="hidden" id="product-package-weight" name="product-package[0][weight]" value="{$product->weight|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-package-height" name="product-package[0][height]" value="{$product->height/100|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-package-width"  name="product-package[0][width]"  value="{$product->width/100|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-package-depth"  name="product-package[0][depth]"  value="{$product->depth/100|escape:'htmlall':'UTF-8'}"/>

        <button class="submit-button" type="button" id="btCalcularFrete" data-loading-text="Carregando...">
            Calcular Frete
        </button>
        <div id="response-error" class="response-error"></div>
        <div id="resultado-frete" class="resultado-frete">
            <table class="table" id="frete-valores">
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<script>
    var formatter = new Intl.NumberFormat('pt-BR', {
      style   : 'currency',
      currency: 'BRL',
    });

    function maskCep(t, mask) {
        var i     = t.value.length;
        var saida = mask.substring(1, 0);
        var texto = mask.substring(i)
        if (texto.substring(0, 1) != saida) {
            t.value += texto.substring(0, 1);
        }
    }

    function addRowTableFrete(nomeServico, imgLogo, deadline, valorServico) {
        return '<tr><td><img src="https://' + imgLogo + '" alt="' + nomeServico + '" title="' + nomeServico + '" width = "180" /> <br/><p style="margin:5px 0 0 0;text-align:center"> ' + nomeServico + ' </p></td><td style="vertical-align: middle;text-align: center;">Entrega em ' + deadline + ' dia(s)<br/>' + formatter.format(valorServico) + ' </td></tr>';
    }

    function addRowError(message) {
        return '<tr><td> ' + message + ' </td></tr>';
    }

    var btnFCFind = document.getElementById('btCalcularFrete');

    btnFCFind.addEventListener('click', function () {
        var productTotalValue = document.getElementById('product-total-price').value;
        var productQuantity   = parseInt(document.getElementById('quantity_wanted').value);

        if (productTotalValue.length) {
          productTotalValue = (productTotalValue.substr(productTotalValue.search(/\d/g))).replace(',', '.');
        }
        else {
          productTotalValue = 0;
        }

        let inputForm = {
            destination      : {
                cep: document.getElementById('fk-cep').value,
            },
            productTotalPrice: productTotalValue * productQuantity,
            packages         : [
                {
                    qtd   : productQuantity,
                    weight: parseFloat(document.getElementById('product-package-weight').value),
                    height: parseFloat(document.getElementById('product-package-height').value),
                    width : parseFloat(document.getElementById('product-package-width' ).value),
                    depth : parseFloat(document.getElementById('product-package-depth' ).value)
                }
            ],
            productType      : document.getElementById('product-type').value,
        };

        btnFCFind.innerHTML    = 'Carregando...';
        btnFCFind.disabled     = true;

        let resultadoFrete     = document.getElementById('resultado-frete')
        let freteValores       = document.querySelector ("#frete-valores tbody");
        let url_shipping_quote = document.getElementById('url_shipping_quote');
        let url                = url_shipping_quote.dataset.action;
        let responseError      = document.getElementById('response-error');

        resultadoFrete.style.display = "none";
        responseError.style.display  = "none";
        freteValores.innerHTML       = "";

        fetch(url, {
            method: "POST",
            body  : JSON.stringify(inputForm)
        })
            .then(response => {
                if (response.status === 200) {
                    return response.json();
                }

                throw new Error('Ops! Houve um erro em nosso servidor.');
            })
            .then(response => {
                let _response = response.response;

                if (_response.success === false) {
                    responseError.innerText     = _response.error;
                    responseError.style.display = "block";
                }
                else {
                    if (_response.data.quotes) {
                        let table = '';

                        _response.data.quotes.forEach(quote => {
                            table += addRowTableFrete(quote.carrier.name, quote.carrier.image, quote.deliveryDeadline, quote.total)
                        });

                        freteValores.innerHTML       = table;
                        resultadoFrete.style.display = "block";
                    }
                }
            })
            .catch(error => {
                responseError.innerText     = error.message;
                responseError.style.display = "block";
            })
            .finally(() => {
                btnFCFind.innerHTML = 'Calcular Frete';
                btnFCFind.disabled  = false;
            });
    });
</script>
