<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">FRETE CLICK</div>
    <div id="url_shipping_quote" class="panel-body" data-action="{$url_shipping_quote|escape:'htmlall':'UTF-8'}">
        <input type="hidden" id="city-origin" name="city-origin" value="{$city_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="cep-origin" name="cep-origin" value="{$cep_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="state-origin" name="state-origin" value="{$state_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="country-origin" name="country-origin"
               value="{$country_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="text" id="fk-cep" value="{$cep|escape:'htmlall':'UTF-8'}"
               onkeypress="maskCep(this, '#####-###')"
               maxlength="9" class="form-control" name="cep-destination" placeholder="Informe CEP de destino" required>
        <input type="hidden" id="product-type" name="product-type" value="{$product->name|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-total-price" name="product-total-price"
               data-value="{$product->price|escape:'htmlall':'UTF-8'}"
               value="{$product->price|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-package-qtd" name="product-package[0][qtd]" value="1"/>
        <input type="hidden" id="product-package-weight" name="product-package[0][weight]"
               value="{$product->weight|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-package-height" name="product-package[0][height]"
               value="{$product->height/100|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-package-width" name="product-package[0][width]"
               value="{$product->width/100|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="product-package-depth" name="product-package[0][depth]"
               value="{$product->depth/100|escape:'htmlall':'UTF-8'}"/>
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
    let productTotalValueEl = document.querySelector('#product-total-price');

    function maskCep(t, mask) {
        var i = t.value.length;
        var saida = mask.substring(1, 0);
        var texto = mask.substring(i)
        if (texto.substring(0, 1) != saida) {
            t.value += texto.substring(0, 1);
        }
    }

    function addRowTableFrete(nomeServico, imgLogo, deadline, valorServico) {
        return '<tr><td><img src="https://' + imgLogo.replace('api', 'app') + '" alt="' + nomeServico + '" title="' + nomeServico + '" width = "180" /> <br/><p style="margin:5px 0 0 0;text-align:center"> ' + nomeServico + ' </p></td><td style="vertical-align: middle;text-align: center;">Entrega em ' + deadline + ' dia(s)<br/>' + valorServico + ' </td></tr>';
    }

    function addRowError(message) {
        return '<tr><td> ' + message + ' </td></tr>';
    }

    document.getElementById("quantity_wanted").addEventListener('change', function () {
        console.log('quantity_wanted');
        document.getElementById("product-package-qtd").value = this.value
        productTotalValueEl.value = productTotalValueEl.dataset.value * parseInt(this.value);
    });

    let btnFCFind = document.querySelector('#btCalcularFrete');

    btnFCFind.addEventListener('click', function () {
        let productTotalValue = document.querySelector('#product-total-price').value;
        productTotalValue = (productTotalValue.substr(productTotalValue.search(/\d/g))).replace(',', '.');

        let inputForm = {
            origin           : {
                cep    : document.querySelector('#cep-origin').value,
                country: document.querySelector('#country-origin').value,
                state  : document.querySelector('#state-origin').value,
                city   : document.querySelector('#city-origin').value
            },
            destination      : {
                cep: document.querySelector('#fk-cep').value,
            },
            productTotalPrice: parseFloat(productTotalValue),
            packages         : [
                {
                    qtd   : parseInt(document.querySelector('#product-package-qtd').value),
                    weight: parseFloat(document.querySelector('#product-package-weight').value),
                    height: parseFloat(document.querySelector('#product-package-height').value),
                    width : parseFloat(document.querySelector('#product-package-width').value),
                    depth : parseFloat(document.querySelector('#product-package-depth').value)
                }
            ],
            productType      : document.querySelector('#product-type').value,
            contact          : null
        };

        btnFCFind.innerHTML    = 'Carregando...';
        btnFCFind.disabled     = true;

        let resultadoFrete     = document.getElementById('resultado-frete')
        let freteValores       = document.querySelector("#frete-valores tbody");
        let url_shipping_quote = document.querySelector('#url_shipping_quote');
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