<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">Frete Click</div>
    <div id="url_shipping_quote" class="panel-body" data-action="{$url_shipping_quote|escape:'htmlall':'UTF-8'}">
        <input type="hidden" id="city-origin" name="city-origin" value="{$city_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="cep-origin" name="cep-origin" value="{$cep_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="state-origin" name="state-origin" value="{$state_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" id="country-origin" name="country-origin"
               value="{$country_origin|escape:'htmlall':'UTF-8'}"/>
        <input type="text" id="fk-cep" value="{$cep|escape:'htmlall':'UTF-8'}"
               onkeypress="maskCep(this, '#####-###')"
               maxlength="9" class="form-control" name="cep-destination" placeholder="CEP de destino" required>
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
        <button class="btn btn-default" type="button" id="btCalcularFrete" data-loading-text="Carregando...">
            Calcular
        </button>
        <div id="resultado-frete" style="padding-top:20px;">
            <table class="table" id="frete-valores">
                <tbody>
                </tbody>
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
        return '<tr><td><img src="https://' + imgLogo.replace('api', 'app') + '" alt="' + nomeServico + '" title="' + nomeServico + '" width = "180" /> <br/><p> ' + nomeServico + ' </p></td><td> Entrega em ' + deadline + ' dia(s) <br/> ' + valorServico + ' </td></tr>';
    }

    function addRowError(message) {
        return '<tr><td> ' + message + ' </td></tr>';
    }

    //
    // jQuery(function ($) {
    //         $(document).ready(function () {
    //             $.fn.extend({
    //                 propAttr: $.fn.prop || $.fn.attr
    //             });
    //             $("[data-field-qty=qty],.cart_quantity_button a").click(function () {
    //                 setTimeout(function () {
    //                     $("#quantity_wanted,.cart_quantity input").trigger('change');
    //                 }, 300);
    //             });
    document.getElementById("quantity_wanted").addEventListener('change', function () {
        console.log('quantity_wanted');
        document.getElementById("product-package-qtd").value = this.value
        productTotalValueEl.value = productTotalValueEl.dataset.value * parseInt(this.value);
    });
    //
    //             $('#fk-cep').keydown(function (event) {
    //                 if (event.keyCode == 13) {
    //                     event.preventDefault();
    //                     event.stopPropagation();
    //                     event.stopImmediatePropagation();
    //                     return false;
    //                 }
    //             });
    //
    let btnFCFind = document.querySelector('#btCalcularFrete');

    btnFCFind.addEventListener('click', function () {
        let productTotalValue = document.querySelector('#product-total-price').value;
        productTotalValue = (productTotalValue.substr(productTotalValue.search(/\d/g))).replace(',', '.');

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
            productTotalPrice: parseFloat(productTotalValue),
            packages: [
                {
                    qtd: parseInt(document.querySelector('#product-package-qtd').value),
                    weight: parseFloat(document.querySelector('#product-package-weight').value),
                    height: parseFloat(document.querySelector('#product-package-height').value),
                    width: parseFloat(document.querySelector('#product-package-width').value),
                    depth: parseFloat(document.querySelector('#product-package-depth').value)
                }
            ],
            productType: document.querySelector('#product-type').value,
            contact: null
        };

        btnFCFind.innerHTML = 'Carregando';
        let resultadoFrete = document.querySelector('#resultado-frete')
        let freteValores = document.querySelector("#frete-valores tbody");
        let url_shipping_quote = document.querySelector('#url_shipping_quote');
        let url = url_shipping_quote.dataset.action;

        resultadoFrete.display = 'none';
        freteValores.innerHTML = "";
        // console.log(document.querySelector('#calcularfrete'));
        // let inputForm = new FormData(document.querySelector('#calcularfrete'));

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
                console.log(response)
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
        // resultadoFrete.display = 'block';
    });

    // $('#resultado-frete').hide();
    // if ($('[name="fkcorreiosg2_cep"]').length > 0) {
    //     $(".cart_quantity input").change(function () {
    //         setTimeout(function () {
    //             $('.fkcorreiosg2-button').trigger('click');
    //         }, 3000);
    //     });
    //     $('#calcular_frete,#box-frete-click').hide();
    //     $('[name="fkcorreiosg2_cep"]').change(function () {
    //         $('#fk-cep').val($('[name="fkcorreiosg2_cep"]').val());
    //         if ($('#fk-cep').val().length >= 9) {
    //             tb_cep_keyup();
    //             setTimeout(function () {
    //                 $('#btCalcularFrete').click();
    //                 $('#box-frete-click').show();
    //             }, 500);
    //
    //         }
    //     });
    //     $('#fk-cep').val($('[name="fkcorreiosg2_cep"]').val());
    //     if ($('#fk-cep').val().length >= 9) {
    //         tb_cep_keyup();
    //         setTimeout(function () {
    //             $('#btCalcularFrete').click();
    //             $('#box-frete-click').show();
    //         }, 500);
    //     }
    //     $('.fkcorreiosg2-button').click(function () {
    //         tb_cep_keyup();
    //         setTimeout(function () {
    //             $('#btCalcularFrete').click();
    //             $('#box-frete-click').show();
    //         }, 500);
    //     });
    //
    //     $("#quantity_wanted").change(function () {
    //         setTimeout(function () {
    //             $('#btCalcularFrete').trigger('click');
    //         }, 1000);
    //     });
    // }
    //
    //
    // /*
    //  delivery_option_radio
    //  $(button).prop('disabled', true);
    //  */
    //
    // $('input[name="fc_transportadora"]').click(function () {
    //     var button = $('[name="processCarrier"]');
    //     var fprice = $(this).attr('data-fprice'),
    //         nome_transportadora = $(this).attr('data-name'),
    //         module_name = $('#module_name').val();
    //     var descricao = '<strong>' + module_name + '</strong><br/>' + 'Transportadora:' + nome_transportadora + '<br/>';
    //     $.ajax({
    //         url: $('#url_transportadora').val(),
    //         type: "post",
    //         dataType: "json",
    //         data: {
    //             quote_id: $(this).val(),
    //             nome_transportadora: nome_transportadora,
    //             valor_frete: $(this).attr('data-price')
    //         },
    //         success: function (json) {
    //             if (json.status === true) {
    //                 $('.delivery_option_radio:checked').closest('tr').find('td.delivery_option_price').prev().html(descricao);
    //                 $('.delivery_option_radio:checked').closest('tr').find('td.delivery_option_price').html(fprice);
    //             }
    //         }
    //     });
    // });
    //
    // $(document).on('submit', 'form[name=carrier_area]', function () {
    //     var valTransportadora = $('input[name="fc_transportadora"]:checked').length;
    //     if (valTransportadora === 0 && $('input[name="fc_transportadora"]').length) {
    //         alert('Selecione uma transportadora');
    //         return false;
    //     }
    // });
    //
    // $(".fc-input-cep").keypress(function (event) {
    //     maskCep(this, "#####-###");
    // });
    //
    //
    // var tb_cep = $('#fk-cep,#cep-origin');
    // if (tb_cep) {
    //     console.log('e');
    //     $(tb_cep).on('keyup', function () {
    //         tb_cep_keyup()
    //     });
    // }
    // console.log('f');
    //
    // })
    // ;
    // }
    // )
    // ;

    // function tb_cep_keyup() {
    //     console.log('k');
    //     ViaCep
    //     var tb_cep, tb_rua, tb_cidade, tb_bairro, se_estado, span_estado, tb_pais;
    //
    //     tb_cep = document.getElementById("cep-origin") ? document.getElementById("cep-origin") : document.getElementById("fk-cep");
    //     tb_rua = document.getElementById("street-origin") ? document.getElementById("street-origin") : document.getElementById("street-destination");
    //     tb_cidade = document.getElementById("city-origin") ? document.getElementById("city-origin") : document.getElementById("city-destination");
    //     tb_bairro = document.getElementById("district-origin") ? document.getElementById("district-origin") : document.getElementById("district-destination");
    //     se_estado = document.getElementById("state-origin") ? document.getElementById("state-origin") : document.getElementById("state-destination");
    //     tb_pais = document.getElementById("country-origin") ? document.getElementById("country-origin") : document.getElementById("country-destination");
    //     if (tb_pais) {
    //         tb_pais.value = "Brasil";
    //     }
    //     var reseta = function () {
    //         tb_cep.disabled = false;
    //         tb_rua.disabled = false;
    //         tb_cidade.disabled = false;
    //         tb_bairro.disabled = false;
    //         se_estado.disabled = false;
    //     };
    //     var num = tb_cep.value.length;
    //     if (num == 9) {
    //         tb_cep.disabled = true;
    //         tb_rua.disabled = true;
    //         tb_cidade.disabled = true;
    //         tb_bairro.disabled = true;
    //         se_estado.disabled = true;
    //
    //         $.ajax({
    //             url: "https://viacep.com.br/ws/" + tb_cep.value + "/json/",
    //             data: null,
    //             success: function (data) {
    //                 if (!data.erro) {
    //                     tb_rua.value = data.logradouro;
    //                     tb_cidade.value = data.localidade;
    //                     tb_bairro.value = data.bairro;
    //                     se_estado.value = data.uf;
    //                 }
    //                 reseta();
    //             },
    //             dataType: "json"
    //         });
    //     }
    //     else {
    //         reseta();
    //     }
    // }
</script>