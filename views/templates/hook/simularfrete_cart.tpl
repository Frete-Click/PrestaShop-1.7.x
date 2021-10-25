<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">FRETE CLICK</div>
    <div class="panel-body">
        <form name="calcular_frete" id="calcular_frete" data-action="{$url_shipping_quote|escape:'htmlall':'UTF-8'}" method="post">

          <input required
            type       ="text"
            id         ="fk-cep"
            value      ="{$cep|escape:'htmlall':'UTF-8'}"
            onkeypress ="maskCep(this, '#####-###')"
            maxlength  ="9"
            class      ="form-control"
            name       ="cep-destination"
            placeholder="CEP de destino"
          >

            <input type="hidden" id="product-type"        value="{$product_name|escape:'htmlall':'UTF-8'}"/>
            <input type="hidden" id="product-total-price" value="{$cart['subtotals']['products']['amount']|escape:'htmlall':'UTF-8'}"/>


            {foreach key=key item=product from=$products}
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][qtd]"    value="{$product['cart_quantity']}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][weight]" value="{$product['weight']}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][height]" value="{$product['height']/100}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][width]"  value="{$product['width']/100}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][depth]"  value="{$product['depth']/100}"/>
            {/foreach}

            <button class="submit-button" type="button" id="btCalcularFrete" data-loading-text="Carregando...">
                Calcular Frete
            </button>
        </form>
        <div id="response-error" class="response-error"></div>
        <div id="resultado-frete" class="resultado-frete">
            <table class="table" id="frete-valores">
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    var formatter = new Intl.NumberFormat('pt-BR', {
      style   : 'currency',
      currency: 'BRL',
    });
    var btnFCFind = document.getElementById('btCalcularFrete');

    function maskCep(t, mask) {
        let i = t.value.length;
        let saida = mask.substring(1, 0);
        let texto = mask.substring(i)
        if (texto.substring(0, 1) !== saida) {
            t.value += texto.substring(0, 1);
        }
    }

    function addRowTableFrete(nomeServico, imgLogo, deadline, valorServico) {
        return '<tr><td><img src="https://' + imgLogo + '" alt="' + nomeServico + '" title="' + nomeServico + '" width = "180" /> <br/><p> ' + nomeServico + ' </p></td><td> Entrega em ' + deadline + ' dia(s) <br/> ' + formatter.format(valorServico) + ' </td></tr>';
    }

    document.getElementById("calcular_frete").addEventListener("submit", function(event) {
        event.preventDefault();
        findShippings()
    });

    btnFCFind.addEventListener('click', function () {
        findShippings()
    });

    function addRowError(message) {
        return '<tr><td> ' + message + ' </td></tr>';
    }

    function findShippings() {
        let productTotalValue = document.getElementById('product-total-price').value;
        productTotalValue = (productTotalValue.substr(productTotalValue.search(/\s/g) + 1)).replace(',', '.');

        let products = document.querySelectorAll('.products');

        let packages = [];

        products.forEach(product => {
            let items = product.name.split('[');
            let index = items[1].substr(0, items[1].length - 1);
            let label = items[2].substr(0, items[2].length - 1);

            if (!packages[index]) {
                packages[index] = {};
            }
            packages[index][label] = product.value;
        });

        packages = packages.map(pkg => {
            pkg.qtd    = parseInt(pkg.qtd);
            pkg.weight = parseFloat(pkg.weight);
            pkg.height = parseFloat(pkg.height);
            pkg.width  = parseFloat(pkg.width);
            pkg.depth  = parseFloat(pkg.depth);

            return pkg;
        });

        let inputForm = {
            destination      : {
                cep: document.getElementById('fk-cep').value,
            },
            productTotalPrice: productTotalValue,
            packages         : packages,
            productType      : document.getElementById('product-type').value
        };

        btnFCFind.innerHTML    = 'Carregando...';
        btnFCFind.disabled     = true;

        let resultadoFrete     = document.getElementById('resultado-frete')
        let freteValores       = document.querySelector ("#frete-valores tbody");
        let responseError      = document.getElementById('response-error');
        let url_shipping_quote = document.getElementById('calcular_frete');
        let url                = url_shipping_quote.dataset.action;

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
    }
</script>
