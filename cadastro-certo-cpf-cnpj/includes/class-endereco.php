<?php
/**
 * Endereço brasileiro: número e bairro nos e-mails, na tela do pedido e em "Minha conta".
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formatação do endereço.
 */
class Endereco {

	/**
	 * Liga os ganchos.
	 */
	public static function iniciar() {
		add_filter( 'woocommerce_localisation_address_formats', array( __CLASS__, 'formato' ) );
		add_filter( 'woocommerce_formatted_address_replacements', array( __CLASS__, 'substituicoes' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'pedido_cobranca' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( __CLASS__, 'pedido_entrega' ), 10, 2 );
		add_filter( 'woocommerce_my_account_my_address_formatted_address', array( __CLASS__, 'minha_conta' ), 10, 3 );
		add_filter( 'woocommerce_get_order_address', array( __CLASS__, 'endereco_do_pedido' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'sem_repeticao_em_minha_conta' ) );
		add_filter( 'woocommerce_address_to_edit', array( __CLASS__, 'ordem_em_minha_conta' ), 20, 2 );
	}

	/**
	 * No formulário de endereço de "Minha conta", o WooCommerce põe os campos do checkout em
	 * blocos no fim. Aqui o número vai para depois da rua e o bairro para depois do complemento.
	 *
	 * @param array  $campos Campos do formulário.
	 * @param string $grupo  billing ou shipping.
	 * @return array
	 */
	public static function ordem_em_minha_conta( $campos, $grupo ) {
		if ( ! is_array( $campos ) || ! in_array( $grupo, array( 'billing', 'shipping' ), true ) ) {
			return $campos;
		}
		$prefixo = '_wc_' . $grupo . '/' . Campos_Blocos::NS . '/';
		$mover   = array(
			$grupo . '_address_1' => $prefixo . 'numero',
			$grupo . '_address_2' => $prefixo . 'bairro',
		);
		$guardados = array();
		foreach ( $mover as $chave ) {
			if ( isset( $campos[ $chave ] ) ) {
				$guardados[ $chave ] = $campos[ $chave ];
				unset( $campos[ $chave ] );
			}
		}
		$saida = array();
		foreach ( $campos as $chave => $campo ) {
			$saida[ $chave ] = $campo;
			if ( isset( $mover[ $chave ], $guardados[ $mover[ $chave ] ] ) ) {
				$saida[ $mover[ $chave ] ] = $guardados[ $mover[ $chave ] ];
				unset( $guardados[ $mover[ $chave ] ] );
			}
		}
		return array_merge( $saida, $guardados );
	}

	/**
	 * Em "Minha conta", o WooCommerce lista embaixo de cada endereço os campos adicionais do
	 * checkout em blocos ("Número: 12", "Bairro: Centro"). Como número e bairro já entram no
	 * endereço formatado, essa lista sai quando os únicos campos adicionais de endereço da
	 * loja são os deste plugin. Se houver campos de outros plugins, a lista do WooCommerce
	 * fica e o endereço formatado deixa de repetir número e bairro.
	 *
	 * @var bool
	 */
	protected static $lista_do_woocommerce = false;

	/**
	 * Decide quem mostra número e bairro em "Minha conta".
	 */
	public static function sem_repeticao_em_minha_conta() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		$frontend = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFieldsFrontend';
		$campos   = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';
		if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Package' ) || ! class_exists( $frontend ) || ! class_exists( $campos ) ) {
			return;
		}
		try {
			$conteiner = \Automattic\WooCommerce\Blocks\Package::container();
			$chaves    = array_keys( (array) $conteiner->get( $campos )->get_fields_for_location( 'address' ) );
			$alheios   = array_filter(
				$chaves,
				function ( $chave ) {
					return 0 !== strpos( $chave, Campos_Blocos::NS . '/' );
				}
			);
			if ( empty( $alheios ) ) {
				remove_action( 'woocommerce_my_account_after_my_address', array( $conteiner->get( $frontend ), 'render_address_fields' ), 10 );
			} else {
				self::$lista_do_woocommerce = true;
			}
		} catch ( \Exception $e ) {
			self::$lista_do_woocommerce = true;
		}
	}

	/**
	 * Formato do endereço no Brasil.
	 *
	 * @param array $formatos Formatos por país.
	 * @return array
	 */
	public static function formato( $formatos ) {
		// Só marcadores padrão: o checkout em blocos monta o cartão de endereço no navegador
		// com este mesmo formato e não conhece marcadores novos (mostraria "{number}" na tela).
		// Número e bairro entram pelas substituições de {address_1} e {address_2}, abaixo.
		$formatos['BR'] = "{company}\n{name}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}";
		return $formatos;
	}

	/**
	 * Número junto da rua ("Rua X, 700") e bairro numa linha depois do complemento.
	 * {number} e {neighborhood} continuam disponíveis para quem usa formato próprio.
	 *
	 * @param array $substituicoes Substituições.
	 * @param array $args          Partes do endereço.
	 * @return array
	 */
	public static function substituicoes( $substituicoes, $args ) {
		$numero = isset( $args['number'] ) && is_scalar( $args['number'] ) ? trim( (string) $args['number'] ) : '';
		$bairro = isset( $args['neighborhood'] ) && is_scalar( $args['neighborhood'] ) ? trim( (string) $args['neighborhood'] ) : '';

		$substituicoes['{number}']       = $numero;
		$substituicoes['{neighborhood}'] = $bairro;

		$pais = isset( $args['country'] ) ? (string) $args['country'] : '';
		if ( 'BR' !== $pais ) {
			return $substituicoes;
		}
		$rua         = isset( $substituicoes['{address_1}'] ) ? (string) $substituicoes['{address_1}'] : '';
		$complemento = isset( $substituicoes['{address_2}'] ) ? (string) $substituicoes['{address_2}'] : '';
		if ( '' !== $numero && '' !== $rua ) {
			$rua .= ', ' . $numero;
		}
		if ( '' !== $bairro ) {
			$complemento = '' === $complemento ? $bairro : $complemento . "\n" . $bairro;
		}
		$substituicoes['{address_1}']       = $rua;
		$substituicoes['{address_2}']       = $complemento;
		$substituicoes['{address_1_upper}'] = function_exists( 'wc_strtoupper' ) ? wc_strtoupper( $rua ) : strtoupper( $rua );
		$substituicoes['{address_2_upper}'] = function_exists( 'wc_strtoupper' ) ? wc_strtoupper( $complemento ) : strtoupper( $complemento );
		return $substituicoes;
	}

	/**
	 * Endereço de cobrança do pedido.
	 *
	 * @param array     $endereco Endereço.
	 * @param \WC_Order $pedido   Pedido.
	 * @return array
	 */
	public static function pedido_cobranca( $endereco, $pedido ) {
		return self::completar( $endereco, $pedido, 'billing' );
	}

	/**
	 * Endereço de entrega do pedido.
	 *
	 * @param array     $endereco Endereço.
	 * @param \WC_Order $pedido   Pedido.
	 * @return array
	 */
	public static function pedido_entrega( $endereco, $pedido ) {
		return self::completar( $endereco, $pedido, 'shipping' );
	}

	/**
	 * Endereço em "Minha conta".
	 *
	 * @param array  $endereco   Endereço.
	 * @param int    $cliente_id Cliente.
	 * @param string $grupo      billing ou shipping.
	 * @return array
	 */
	public static function minha_conta( $endereco, $cliente_id, $grupo ) {
		if ( ! is_array( $endereco ) || self::$lista_do_woocommerce ) {
			return $endereco;
		}
		$endereco['number']       = (string) get_user_meta( $cliente_id, $grupo . '_number', true );
		$endereco['neighborhood'] = (string) get_user_meta( $cliente_id, $grupo . '_neighborhood', true );
		return $endereco;
	}

	/**
	 * $order->get_address() com número e bairro.
	 *
	 * @param array     $endereco Endereço.
	 * @param string    $grupo    billing ou shipping.
	 * @param \WC_Order $pedido   Pedido.
	 * @return array
	 */
	public static function endereco_do_pedido( $endereco, $grupo, $pedido ) {
		return self::completar( $endereco, $pedido, $grupo );
	}

	/**
	 * Põe número e bairro no endereço.
	 *
	 * @param array     $endereco Endereço.
	 * @param \WC_Order $pedido   Pedido.
	 * @param string    $grupo    billing ou shipping.
	 * @return array
	 */
	protected static function completar( $endereco, $pedido, $grupo ) {
		if ( ! is_array( $endereco ) || ! ( $pedido instanceof \WC_Order ) || ! in_array( $grupo, array( 'billing', 'shipping' ), true ) ) {
			return $endereco;
		}
		$endereco['number']       = Plugin::ler( $pedido, $grupo, 'number' );
		$endereco['neighborhood'] = Plugin::ler( $pedido, $grupo, 'neighborhood' );
		return $endereco;
	}
}
