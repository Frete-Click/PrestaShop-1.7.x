<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">Frete Click</div>
    <div class="panel-body">
        <form name="calcular_frete" id="calcular_frete" data-action="{$url_shipping_quote|escape:'htmlall':'UTF-8'}"
              method="post">
            <input type="hidden" id="city-origin" value="{$city_origin|escape:'htmlall':'UTF-8'}"/>
            <input type="hidden" id="cep-origin" value="{$cep_origin|escape:'htmlall':'UTF-8'}"/>
            <input type="hidden" id="state-origin" value="{$state_origin|escape:'htmlall':'UTF-8'}"/>
            <input type="hidden" id="country-origin" value="{$country_origin|escape:'htmlall':'UTF-8'}"/>

            <input type="hidden" id="product-type" value="{$product_name|escape:'htmlall':'UTF-8'}"/>
            <input type="hidden" id="product-total-price"
                   value="{$cart['subtotals']['products']['amount']|escape:'htmlall':'UTF-8'}"/>

            <input type="text" id="fk-cep" value="{$cep|escape:'htmlall':'UTF-8'}"
                   onkeypress="maskCep(this, '#####-###')" maxlength="9" class="form-control" name="cep-destination"
                   placeholder="CEP de destino" required>
            {foreach key=key item=product from=$products}
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][qtd]"
                       value="{$product['cart_quantity']}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][weight]"
                       value="{$product['weight']}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][height]"
                       value="{$product['height']/100}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][width]"
                       value="{$product['width']/100}"/>
                <input type="hidden" class="products" name="product-package[{$key|escape:'htmlall':'UTF-8'}][depth]"
                       value="{$product['depth']/100}"/>
            {/foreach}
            <button class="btn btn-default" type="button" id="btCalcularFrete" data-loading-text="Carregando...">
                Calcular
            </button>
        </form>
        <div id="resultado-frete" style="padding-top:20px;">
            <table class="table" id="frete-valores">
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let btnFCFind = document.querySelector('#btCalcularFrete');

    function maskCep(t, mask) {
        let i = t.value.length;
        let saida = mask.substring(1, 0);
        let texto = mask.substring(i)
        if (texto.substring(0, 1) !== saida) {
            t.value += texto.substring(0, 1);
        }
    }

    function addRowTableFrete(nomeServico, imgLogo, deadline, valorServico) {
        return '<tr><td><img src="https://' + imgLogo.replace('api', 'app') + '" alt="' + nomeServico + '" title="' + nomeServico + '" width = "180" /> <br/><p> ' + nomeServico + ' </p></td><td> Entrega em ' + deadline + ' dia(s) <br/> ' + valorServico + ' </td></tr>';
    }

    document.getElementById("calcular_frete").addEventListener("submit", function(event){
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
        let productTotalValue = document.querySelector('#product-total-price').value;
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
            pkg.qtd = parseInt(pkg.qtd);
            pkg.weight = parseFloat(pkg.weight);
            pkg.height = parseFloat(pkg.height);
            pkg.width = parseFloat(pkg.width);
            pkg.depth = parseFloat(pkg.depth);
            return pkg;
        });

        let inputForm = {
            origin: {
                cep: document.querySelector('#cep-origin').value,
                country: document.querySelector('#country-origin').value,
                state: document.querySelector('#state-origin').value,
                city: document.querySelector('#city-origin').value
            },
            destination: {
                cep: document.querySelector('#fk-cep').value,
            },
            productTotalPrice: productTotalValue,
            packages: packages,
            productType: document.querySelector('#product-type').value,
            contact: null
        };

        btnFCFind.innerHTML = 'Carregando';
        let resultadoFrete = document.querySelector('#resultado-frete')
        let freteValores = document.querySelector("#frete-valores tbody");
        let url_shipping_quote = document.querySelector('#calcular_frete');
        let url = url_shipping_quote.dataset.action;

        resultadoFrete.display = 'none';
        freteValores.innerHTML = "";

        fetch(url, {
            method: "POST",
            body: JSON.stringify(inputForm)
        })
            .then(response => {
                btnFCFind.innerHTML = 'Calular';
                if (response.status === 200) {
                    return response.json();
                } else {
                    throw new Error('Ops! Houve um erro em nosso servidor.');
                }
            })
            .then(response => {
                if (response.quotes.length > 0) {
                    let table = '';
                    response.quotes.forEach(quote => {
                        table += addRowTableFrete(quote.carrier.name, quote.carrier.image, quote.deliveryDeadline, quote.total)
                    });
                    freteValores.innerHTML = table;
                } else {
                    //erro
                    if (typeof response.data.response.error == "string") {
                        freteValores.innerHTML = addRowError(response.data.response.error);
                    } else if (typeof response.data.response.error == "object") {
                        let erros = response.data.response.error;
                        if (erros.length > 0) {
                            let errors = '';
                            for (var i = 0; i < erros.length; i++) {
                                errors += addRowError(erros[i].message);
                            }
                            freteValores.innerHTML = errors;
                        } else {
                            $("#frete-valores tbody").append(addRowError(erros.message));
                        }
                    }
                    resultadoFrete.display = 'block';
                }
            })
            .catch(error => {
                btnFCFind.innerHTML = 'Calular';
                console.error(error);
            });
    }
</script>