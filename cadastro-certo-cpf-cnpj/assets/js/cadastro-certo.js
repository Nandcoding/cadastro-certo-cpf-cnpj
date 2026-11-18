/**
 * Cadastro Certo: máscaras de CPF, CNPJ (com letras), celular e data, e troca de campos
 * por tipo de pessoa no checkout clássico.
 *
 * Funciona sem jQuery. No checkout em blocos (React), o valor é trocado pelo "setter"
 * nativo do campo antes de o React ler o evento, para o React guardar o valor já pontuado.
 */
( function () {
	'use strict';

	var config = window.cadcertoConfig || {};

	function soNumeros( v ) {
		return String( v ).replace( /[^0-9]/g, '' );
	}

	// Aplica um molde: 9 = número, A = letra ou número (maiúscula).
	function moldar( valor, molde ) {
		var limpo = String( valor ).toUpperCase().replace( /[^0-9A-Z]/g, '' );
		var saida = '';
		var i = 0;
		var j;
		var c;
		for ( j = 0; j < molde.length && i < limpo.length; j++ ) {
			c = molde.charAt( j );
			if ( '9' === c ) {
				while ( i < limpo.length && ! /[0-9]/.test( limpo.charAt( i ) ) ) {
					i++;
				}
				if ( i >= limpo.length ) {
					break;
				}
				saida += limpo.charAt( i++ );
			} else if ( 'A' === c ) {
				saida += limpo.charAt( i++ );
			} else {
				saida += c;
			}
		}
		return saida;
	}

	var formatos = {
		cpf: function ( v ) {
			return moldar( soNumeros( v ), '999.999.999-99' );
		},
		cnpj: function ( v ) {
			return moldar( v, 'AA.AAA.AAA/AAAA-99' );
		},
		celular: function ( v ) {
			var n = soNumeros( v ).slice( 0, 11 );
			return moldar( n, n.length > 10 ? '(99) 99999-9999' : '(99) 9999-9999' );
		},
		data: function ( v ) {
			return moldar( soNumeros( v ), '99/99/9999' );
		},
		ie: function ( v ) {
			return String( v ).toUpperCase();
		}
	};

	// Tipo de máscara de um campo, pelo id do checkout clássico ou pelo atributo do checkout em blocos.
	function tipoDoCampo( el ) {
		if ( ! el || 'INPUT' !== el.tagName ) {
			return null;
		}
		var marcado = el.getAttribute( 'data-cadcerto' );
		if ( marcado && formatos[ marcado ] ) {
			return marcado;
		}
		var mapa = {
			billing_cpf: 'cpf',
			billing_cnpj: 'cnpj',
			billing_cellphone: 'celular',
			billing_birthdate: 'data',
			billing_ie: 'ie'
		};
		return mapa[ el.id ] || null;
	}

	var setterNativo = Object.getOwnPropertyDescriptor( window.HTMLInputElement.prototype, 'value' ).set;

	function aoDigitar( evento ) {
		if ( '1' !== config.mascaras ) {
			return;
		}
		var el = evento.target;
		var tipo = tipoDoCampo( el );
		if ( ! tipo ) {
			return;
		}
		// Apagando: não reformata, para o cliente conseguir corrigir.
		if ( evento.inputType && 0 === evento.inputType.indexOf( 'delete' ) ) {
			return;
		}
		var novo = formatos[ tipo ]( el.value );
		if ( novo !== el.value ) {
			setterNativo.call( el, novo );
		}
	}

	// Fase de captura: roda antes do React.
	document.addEventListener( 'input', aoDigitar, true );

	/* Checkout clássico: mostra CPF ou CNPJ conforme o tipo de pessoa e o país. */

	function linha( id ) {
		return document.getElementById( id + '_field' );
	}

	function mostrar( id, sim ) {
		var l = linha( id );
		if ( l ) {
			l.style.display = sim ? '' : 'none';
		}
	}

	function atualizarClassico() {
		var seletor = document.getElementById( 'billing_persontype' );
		var pais = document.getElementById( 'billing_country' );
		var fora = '1' === config.somenteBrasil && pais && pais.value && 'BR' !== pais.value;
		var tipo = config.tipoPessoa;
		var pf = 'fisica' === tipo;
		var pj = 'juridica' === tipo;

		if ( 'ambos' === tipo && seletor ) {
			mostrar( 'billing_persontype', ! fora );
			pf = '1' === seletor.value;
			pj = '2' === seletor.value;
		}
		mostrar( 'billing_cpf', ! fora && pf );
		mostrar( 'billing_cnpj', ! fora && pj );
		mostrar( 'billing_ie', ! fora && pj );
		if ( 'fisica' !== tipo ) {
			mostrar( 'billing_company', fora || pj );
		}
	}

	function iniciarClassico() {
		if ( ! document.getElementById( 'billing_cpf' ) && ! document.getElementById( 'billing_cnpj' ) ) {
			return;
		}
		atualizarClassico();
		document.addEventListener( 'change', function ( e ) {
			if ( e.target && ( 'billing_persontype' === e.target.id || 'billing_country' === e.target.id ) ) {
				atualizarClassico();
			}
		} );
		// O seletor de país do WooCommerce (selectWoo) avisa a mudança só pelo jQuery.
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'change', '#billing_country', atualizarClassico );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', iniciarClassico );
	} else {
		iniciarClassico();
	}
}() );
