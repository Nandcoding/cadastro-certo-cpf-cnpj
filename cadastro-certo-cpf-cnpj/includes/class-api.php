<?php
/**
 * Campos brasileiros na API REST do WooCommerce (pedidos e clientes).
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Acrescenta à resposta da API REST os mesmos campos, com os mesmos nomes e formatos,
 * que o Brazilian Market on WooCommerce acrescentava (billing.cpf, billing.number…).
 */
class Api {

	/**
	 * Liga os ganchos.
	 */
	public static function iniciar() {
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( __CLASS__, 'pedido' ), 100, 2 );
		add_filter( 'woocommerce_rest_prepare_customer', array( __CLASS__, 'cliente' ), 100, 2 );
		add_filter( 'woocommerce_rest_shop_order_schema', array( __CLASS__, 'esquema' ), 100 );
		add_filter( 'woocommerce_rest_customer_schema', array( __CLASS__, 'esquema' ), 100 );
	}

	/**
	 * Tira pontos, barras e traços (como o plugin antigo). Letras do CNPJ novo continuam.
	 *
	 * @param string $valor Valor.
	 * @return string
	 */
	protected static function sem_pontuacao( $valor ) {
		return str_replace( array( '.', '-', '/' ), '', (string) $valor );
	}

	/**
	 * F ou J, como no plugin antigo.
	 *
	 * @param string $tipo '1', '2' ou vazio.
	 * @return string
	 */
	protected static function tipo( $tipo ) {
		if ( '2' === (string) $tipo ) {
			return 'J';
		}
		if ( '1' === (string) $tipo ) {
			return 'F';
		}
		$config = Opcoes::get( 'tipo_pessoa' );
		return 'fisica' === $config ? 'F' : ( 'juridica' === $config ? 'J' : '' );
	}

	/**
	 * Data dd/mm/aaaa para aaaa-mm-ddT00:00:00.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	protected static function data( $data ) {
		$iso = Documentos::data_iso( $data );
		return '' === $iso ? '' : $iso . 'T00:00:00';
	}

	/**
	 * Monta os campos a partir de um pedido ou cliente.
	 *
	 * @param \WC_Data $objeto Pedido ou cliente.
	 * @return array
	 */
	protected static function campos( $objeto ) {
		return array(
			'billing'  => array(
				'number'       => Plugin::ler( $objeto, 'billing', 'number' ),
				'neighborhood' => Plugin::ler( $objeto, 'billing', 'neighborhood' ),
				'persontype'   => self::tipo( Plugin::ler( $objeto, 'billing', 'persontype' ) ),
				'cpf'          => self::sem_pontuacao( Plugin::ler( $objeto, 'billing', 'cpf' ) ),
				'rg'           => self::sem_pontuacao( Plugin::ler( $objeto, 'billing', 'rg' ) ),
				'cnpj'         => self::sem_pontuacao( Plugin::ler( $objeto, 'billing', 'cnpj' ) ),
				'ie'           => self::sem_pontuacao( Plugin::ler( $objeto, 'billing', 'ie' ) ),
				'birthdate'    => self::data( Plugin::ler( $objeto, 'billing', 'birthdate' ) ),
				'gender'       => substr( Plugin::ler( $objeto, 'billing', 'gender' ), 0, 1 ),
				'cellphone'    => Plugin::ler( $objeto, 'billing', 'cellphone' ),
			),
			'shipping' => array(
				'number'       => Plugin::ler( $objeto, 'shipping', 'number' ),
				'neighborhood' => Plugin::ler( $objeto, 'shipping', 'neighborhood' ),
			),
		);
	}

	/**
	 * Junta os campos na resposta.
	 *
	 * @param \WP_REST_Response $resposta Resposta.
	 * @param array             $campos   Campos.
	 * @return \WP_REST_Response
	 */
	protected static function juntar( $resposta, $campos ) {
		foreach ( $campos as $grupo => $valores ) {
			if ( isset( $resposta->data[ $grupo ] ) && is_array( $resposta->data[ $grupo ] ) ) {
				$resposta->data[ $grupo ] = array_merge( $resposta->data[ $grupo ], $valores );
			}
		}
		return $resposta;
	}

	/**
	 * Pedido.
	 *
	 * @param \WP_REST_Response $resposta Resposta.
	 * @param \WC_Order         $pedido   Pedido.
	 * @return \WP_REST_Response
	 */
	public static function pedido( $resposta, $pedido ) {
		if ( ! $pedido instanceof \WC_Order ) {
			return $resposta;
		}
		return self::juntar( $resposta, self::campos( $pedido ) );
	}

	/**
	 * Cliente.
	 *
	 * @param \WP_REST_Response $resposta Resposta.
	 * @param \WP_User          $usuario  Usuário.
	 * @return \WP_REST_Response
	 */
	public static function cliente( $resposta, $usuario ) {
		if ( ! $usuario instanceof \WP_User ) {
			return $resposta;
		}
		return self::juntar( $resposta, self::campos( new \WC_Customer( $usuario->ID ) ) );
	}

	/**
	 * Descreve os campos no esquema da API.
	 *
	 * @param array $propriedades Propriedades.
	 * @return array
	 */
	public static function esquema( $propriedades ) {
		$descricoes = array(
			'billing'  => array(
				'number'       => __( 'Número.', 'cadastro-certo-cpf-cnpj' ),
				'neighborhood' => __( 'Bairro.', 'cadastro-certo-cpf-cnpj' ),
				'persontype'   => __( 'Tipo de pessoa: F (física) ou J (jurídica).', 'cadastro-certo-cpf-cnpj' ),
				'cpf'          => __( 'CPF, sem pontuação.', 'cadastro-certo-cpf-cnpj' ),
				'rg'           => __( 'RG.', 'cadastro-certo-cpf-cnpj' ),
				'cnpj'         => __( 'CNPJ, sem pontuação; pode ter letras (CNPJ alfanumérico).', 'cadastro-certo-cpf-cnpj' ),
				'ie'           => __( 'Inscrição estadual.', 'cadastro-certo-cpf-cnpj' ),
				'birthdate'    => __( 'Data de nascimento.', 'cadastro-certo-cpf-cnpj' ),
				'gender'       => __( 'Sexo (inicial).', 'cadastro-certo-cpf-cnpj' ),
				'cellphone'    => __( 'Celular.', 'cadastro-certo-cpf-cnpj' ),
			),
			'shipping' => array(
				'number'       => __( 'Número.', 'cadastro-certo-cpf-cnpj' ),
				'neighborhood' => __( 'Bairro.', 'cadastro-certo-cpf-cnpj' ),
			),
		);
		foreach ( $descricoes as $grupo => $campos ) {
			foreach ( $campos as $campo => $descricao ) {
				$propriedades[ $grupo ]['properties'][ $campo ] = array(
					'description' => $descricao,
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				);
			}
		}
		return $propriedades;
	}
}
