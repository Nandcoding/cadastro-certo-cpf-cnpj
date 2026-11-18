<?php
/**
 * Campos no checkout em blocos (API de campos adicionais do WooCommerce).
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra os campos no checkout em blocos e espelha cada valor nas chaves antigas
 * (_billing_cpf no pedido, billing_cpf no cliente etc.).
 *
 * O WooCommerce grava os campos adicionais em chaves próprias ("_wc_other/cadastro-certo/cpf").
 * Para o resto da loja, a fonte de verdade continua sendo a chave antiga: as cópias do
 * WooCommerce servem só para o checkout em blocos e são descartadas quando a chave antiga
 * muda por outro caminho (painel, checkout clássico).
 */
class Campos_Blocos {

	const NS = 'cadastro-certo';

	/**
	 * Campo adicional => [grupo da chave antiga, campo antigo]. Grupo null = segue o grupo do endereço.
	 *
	 * @return array
	 */
	public static function mapa() {
		return array(
			'tipo-pessoa'  => array( 'billing', 'persontype' ),
			'cpf'          => array( 'billing', 'cpf' ),
			'razao-social' => array( 'billing', 'company' ),
			'cnpj'         => array( 'billing', 'cnpj' ),
			'ie'           => array( 'billing', 'ie' ),
			'celular'      => array( 'billing', 'cellphone' ),
			'nascimento'   => array( 'billing', 'birthdate' ),
			'sexo'         => array( 'billing', 'gender' ),
			'numero'       => array( null, 'number' ),
			'bairro'       => array( null, 'neighborhood' ),
		);
	}

	/**
	 * Liga os ganchos.
	 */
	public static function iniciar() {
		add_action( 'woocommerce_init', array( __CLASS__, 'registrar' ) );
		add_action( 'woocommerce_set_additional_field_value', array( __CLASS__, 'espelhar' ), 10, 4 );
		add_action( 'woocommerce_blocks_validate_location_contact_fields', array( __CLASS__, 'validar_contato' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'completar_pedido' ), 10, 2 );
		foreach ( array_keys( self::mapa() ) as $campo ) {
			add_filter( 'woocommerce_get_default_value_for_' . self::NS . '/' . $campo, array( __CLASS__, 'valor_padrao' ), 10, 3 );
		}
	}

	/**
	 * A API existe nesta versão do WooCommerce?
	 *
	 * @return bool
	 */
	public static function disponivel() {
		return function_exists( 'woocommerce_register_additional_checkout_field' );
	}

	/**
	 * Regra (JSON Schema) que confere o tipo de pessoa escolhido no checkout.
	 *
	 * @param string $tipo '1' ou '2'.
	 * @return array
	 */
	protected static function regra_tipo( $tipo ) {
		return array(
			'type'       => 'object',
			'properties' => array(
				'customer' => array(
					'type'       => 'object',
					'properties' => array(
						'additional_fields' => array(
							'type'       => 'object',
							'properties' => array(
								self::NS . '/tipo-pessoa' => array( 'const' => $tipo ),
							),
							'required'   => array( self::NS . '/tipo-pessoa' ),
						),
					),
					'required'   => array( 'additional_fields' ),
				),
			),
			'required'   => array( 'customer' ),
		);
	}

	/**
	 * Regra que confere se o país de cobrança foi escolhido e não é o Brasil.
	 *
	 * @return array
	 */
	protected static function regra_fora_do_brasil() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'customer' => array(
					'type'       => 'object',
					'properties' => array(
						'billing_address' => array(
							'type'       => 'object',
							'properties' => array(
								'country' => array(
									'type'      => 'string',
									'minLength' => 1,
									'not'       => array( 'const' => 'BR' ),
								),
							),
							'required'   => array( 'country' ),
						),
					),
					'required'   => array( 'billing_address' ),
				),
			),
			'required'   => array( 'customer' ),
		);
	}

	/**
	 * Junta regras com "anyOf" (vale se qualquer uma valer).
	 *
	 * @param array $regras Regras.
	 * @return array|bool
	 */
	protected static function qualquer( $regras ) {
		$regras = array_values( array_filter( $regras ) );
		if ( empty( $regras ) ) {
			return false;
		}
		if ( 1 === count( $regras ) ) {
			return $regras[0];
		}
		return array( 'anyOf' => $regras );
	}

	/**
	 * Regra "não": vale quando a regra dada não vale.
	 *
	 * @param array $regra Regra.
	 * @return array
	 */
	protected static function nao( $regra ) {
		return array( 'not' => $regra );
	}

	/**
	 * Registra os campos.
	 */
	public static function registrar() {
		if ( ! self::disponivel() ) {
			return;
		}

		$fora        = Opcoes::ligado( 'somente_brasil' ) ? self::regra_fora_do_brasil() : null;
		$seletor     = Opcoes::usa_seletor();
		$e_pf        = self::regra_tipo( '1' );
		$e_pj        = self::regra_tipo( '2' );
		$no_brasil   = $fora ? self::nao( $fora ) : true;
		$obrigatorio = function ( $regra_tipo ) use ( $fora ) {
			// Obrigatório quando o tipo bate e o cliente não está fora do Brasil.
			$regras = array( $regra_tipo );
			if ( $fora ) {
				$regras[] = self::nao( $fora );
			}
			return array( 'allOf' => $regras );
		};

		if ( $seletor ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::NS . '/tipo-pessoa',
					'label'                      => __( 'Tipo de pessoa', 'cadastro-certo-cpf-cnpj' ),
					'location'                   => 'contact',
					'type'                       => 'select',
					'placeholder'                => __( 'Pessoa física ou jurídica?', 'cadastro-certo-cpf-cnpj' ),
					'options'                    => array(
						array(
							'value' => '1',
							'label' => __( 'Pessoa física (CPF)', 'cadastro-certo-cpf-cnpj' ),
						),
						array(
							'value' => '2',
							'label' => __( 'Pessoa jurídica (CNPJ)', 'cadastro-certo-cpf-cnpj' ),
						),
					),
					'required'                   => $fora ? self::nao( $fora ) : true,
					'hidden'                     => $fora ? $fora : false,
					'show_in_order_confirmation' => true,
				)
			);
		}

		if ( Opcoes::usa_cpf() ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::NS . '/cpf',
					'label'                      => __( 'CPF', 'cadastro-certo-cpf-cnpj' ),
					'optionalLabel'              => __( 'CPF', 'cadastro-certo-cpf-cnpj' ),
					'location'                   => 'contact',
					'type'                       => 'text',
					'required'                   => $seletor ? $obrigatorio( $e_pf ) : $no_brasil,
					'hidden'                     => $seletor ? self::qualquer( array( $e_pj, $fora ) ) : ( $fora ? $fora : false ),
					'attributes'                 => array(
						'autocomplete'  => 'off',
						'maxLength'     => 14,
						'data-cadcerto' => 'cpf',
					),
					'sanitize_callback'          => array( __CLASS__, 'limpar_cpf' ),
					'validate_callback'          => array( __CLASS__, 'conferir_cpf' ),
					'show_in_order_confirmation' => true,
				)
			);
		}

		if ( Opcoes::usa_cnpj() ) {
			$oculto_pj = $seletor ? self::qualquer( array( $e_pf, $fora ) ) : ( $fora ? $fora : false );
			$exige_pj  = $seletor ? $obrigatorio( $e_pj ) : $no_brasil;

			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::NS . '/razao-social',
					'label'                      => __( 'Razão social', 'cadastro-certo-cpf-cnpj' ),
					'optionalLabel'              => __( 'Razão social', 'cadastro-certo-cpf-cnpj' ),
					'location'                   => 'contact',
					'type'                       => 'text',
					'required'                   => $exige_pj,
					'hidden'                     => $oculto_pj,
					'attributes'                 => array( 'autocomplete' => 'organization' ),
					'show_in_order_confirmation' => true,
				)
			);
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::NS . '/cnpj',
					'label'                      => __( 'CNPJ (com ou sem letras)', 'cadastro-certo-cpf-cnpj' ),
					'optionalLabel'              => __( 'CNPJ (com ou sem letras)', 'cadastro-certo-cpf-cnpj' ),
					'location'                   => 'contact',
					'type'                       => 'text',
					'required'                   => $exige_pj,
					'hidden'                     => $oculto_pj,
					'attributes'                 => array(
						'autocomplete'   => 'off',
						'autocapitalize' => 'characters',
						'maxLength'      => 18,
						'data-cadcerto'  => 'cnpj',
					),
					'sanitize_callback'          => array( __CLASS__, 'limpar_cnpj' ),
					'validate_callback'          => array( __CLASS__, 'conferir_cnpj' ),
					'show_in_order_confirmation' => true,
				)
			);
			if ( 'nao' !== Opcoes::get( 'ie' ) ) {
				woocommerce_register_additional_checkout_field(
					array(
						'id'                         => self::NS . '/ie',
						'label'                      => __( 'Inscrição estadual (número ou ISENTO)', 'cadastro-certo-cpf-cnpj' ),
						'location'                   => 'contact',
						'type'                       => 'text',
						'required'                   => 'obrigatoria' === Opcoes::get( 'ie' ) ? $exige_pj : false,
						'hidden'                     => $oculto_pj,
						'attributes'                 => array(
							'autocomplete'   => 'off',
							'autocapitalize' => 'characters',
							'data-cadcerto'  => 'ie',
						),
						'sanitize_callback'          => array( Documentos::class, 'normalizar_ie' ),
						'validate_callback'          => array( __CLASS__, 'conferir_ie' ),
						'show_in_order_confirmation' => true,
					)
				);
			}
		}

		if ( 'nao' !== Opcoes::get( 'celular' ) ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::NS . '/celular',
					'label'                      => __( 'Celular', 'cadastro-certo-cpf-cnpj' ),
					'location'                   => 'contact',
					'type'                       => 'text',
					'required'                   => 'obrigatorio' === Opcoes::get( 'celular' ),
					'attributes'                 => array(
						'autocomplete'  => 'tel',
						'maxLength'     => 15,
						'data-cadcerto' => 'celular',
					),
					'show_in_order_confirmation' => true,
				)
			);
		}

		if ( Opcoes::ligado( 'nascimento' ) ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::NS . '/nascimento',
					'label'                      => __( 'Data de nascimento', 'cadastro-certo-cpf-cnpj' ),
					'location'                   => 'contact',
					'type'                       => 'date',
					'required'                   => true,
					'max'                        => 'P0D',
					'show_in_order_confirmation' => true,
				)
			);
		}

		if ( Opcoes::ligado( 'sexo' ) ) {
			$opcoes = array();
			foreach ( Plugin::generos() as $valor => $rotulo ) {
				$opcoes[] = array(
					'value' => $valor,
					'label' => $rotulo,
				);
			}
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::NS . '/sexo',
					'label'                      => __( 'Sexo', 'cadastro-certo-cpf-cnpj' ),
					'location'                   => 'contact',
					'type'                       => 'select',
					'options'                    => $opcoes,
					'required'                   => true,
					'show_in_order_confirmation' => true,
				)
			);
		}

		// Número e bairro valem para cobrança e entrega. Não aparecem soltos na confirmação
		// porque já entram no endereço formatado (ver Endereco).
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => self::NS . '/numero',
				'label'                      => __( 'Número', 'cadastro-certo-cpf-cnpj' ),
				'location'                   => 'address',
				'type'                       => 'text',
				'required'                   => true,
				'attributes'                 => array( 'maxLength' => 20 ),
				'show_in_order_confirmation' => false,
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => self::NS . '/bairro',
				'label'                      => __( 'Bairro', 'cadastro-certo-cpf-cnpj' ),
				'location'                   => 'address',
				'type'                       => 'text',
				'required'                   => Opcoes::ligado( 'bairro_obrigatorio' ),
				'show_in_order_confirmation' => false,
			)
		);
	}

	/**
	 * Formata o CPF.
	 *
	 * @param mixed $valor Valor.
	 * @return string
	 */
	public static function limpar_cpf( $valor ) {
		return Documentos::formatar_cpf( is_scalar( $valor ) ? (string) $valor : '' );
	}

	/**
	 * Formata o CNPJ.
	 *
	 * @param mixed $valor Valor.
	 * @return string
	 */
	public static function limpar_cnpj( $valor ) {
		return Documentos::formatar_cnpj( is_scalar( $valor ) ? (string) $valor : '' );
	}

	/**
	 * Valida o CPF.
	 *
	 * @param string $valor Valor.
	 * @return \WP_Error|null
	 */
	public static function conferir_cpf( $valor ) {
		if ( '' !== (string) $valor && Opcoes::ligado( 'validar_cpf' ) && ! Documentos::cpf_valido( $valor ) ) {
			return new \WP_Error( 'cadcerto_cpf_invalido', __( 'O CPF informado não é válido. Confira os números.', 'cadastro-certo-cpf-cnpj' ) );
		}
		return null;
	}

	/**
	 * Valida o CNPJ (numérico ou alfanumérico).
	 *
	 * @param string $valor Valor.
	 * @return \WP_Error|null
	 */
	public static function conferir_cnpj( $valor ) {
		if ( '' !== (string) $valor && Opcoes::ligado( 'validar_cnpj' ) && ! Documentos::cnpj_valido( $valor ) ) {
			return new \WP_Error( 'cadcerto_cnpj_invalido', __( 'O CNPJ informado não é válido. Confira letras e números (o CNPJ novo pode ter letras).', 'cadastro-certo-cpf-cnpj' ) );
		}
		return null;
	}

	/**
	 * Valida a forma da inscrição estadual.
	 *
	 * @param string $valor Valor.
	 * @return \WP_Error|null
	 */
	public static function conferir_ie( $valor ) {
		if ( '' !== (string) $valor && ! Documentos::ie_aceitavel( $valor ) ) {
			return new \WP_Error( 'cadcerto_ie_invalida', __( 'A inscrição estadual deve ter só números (com ou sem pontos) ou a palavra ISENTO.', 'cadastro-certo-cpf-cnpj' ) );
		}
		return null;
	}

	/**
	 * Conferência final no servidor, independente das regras de exibição: o documento do
	 * tipo de pessoa escolhido tem de estar preenchido.
	 *
	 * @param \WP_Error $erros  Erros.
	 * @param array     $campos Valores dos campos de contato.
	 * @param string    $grupo  Grupo.
	 */
	public static function validar_contato( $erros, $campos, $grupo ) {
		if ( apply_filters( 'cadcerto_desligar_validacao', false ) ) {
			return;
		}
		// Só no checkout: em "Minha conta" o cliente pode salvar dados parciais.
		if ( ! self::em_checkout_da_store_api() ) {
			return;
		}
		if ( Opcoes::ligado( 'somente_brasil' ) && function_exists( 'WC' ) && WC()->customer && '' !== WC()->customer->get_billing_country() && 'BR' !== WC()->customer->get_billing_country() ) {
			return;
		}
		$tipo = Opcoes::usa_seletor() ? (string) ( isset( $campos[ self::NS . '/tipo-pessoa' ] ) ? $campos[ self::NS . '/tipo-pessoa' ] : '' ) : ( Opcoes::usa_cpf() ? '1' : '2' );
		if ( '1' === $tipo && empty( $campos[ self::NS . '/cpf' ] ) ) {
			$erros->add( 'cadcerto_cpf_obrigatorio', __( 'Informe o CPF.', 'cadastro-certo-cpf-cnpj' ) );
		}
		if ( '2' === $tipo && empty( $campos[ self::NS . '/cnpj' ] ) ) {
			$erros->add( 'cadcerto_cnpj_obrigatorio', __( 'Informe o CNPJ.', 'cadastro-certo-cpf-cnpj' ) );
		}
	}

	/**
	 * A requisição é o envio final do checkout pela Store API?
	 *
	 * @return bool
	 */
	protected static function em_checkout_da_store_api() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		// Só o envio final (POST). Enquanto o cliente digita, o checkout manda atualizações parciais (PUT).
		$metodo = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$rota   = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		return 'POST' === $metodo && false !== strpos( $rota, '/wc/store' ) && false !== strpos( $rota, '/checkout' );
	}

	/**
	 * Copia o valor de um campo adicional para a chave antiga.
	 *
	 * @param string   $chave  Id do campo (ex.: cadastro-certo/cpf).
	 * @param mixed    $valor  Valor.
	 * @param string   $grupo  billing, shipping ou other.
	 * @param \WC_Data $objeto Pedido ou cliente.
	 */
	public static function espelhar( $chave, $valor, $grupo, $objeto ) {
		if ( 0 !== strpos( (string) $chave, self::NS . '/' ) || ! ( $objeto instanceof \WC_Order || $objeto instanceof \WC_Customer ) ) {
			return;
		}
		$campo = substr( $chave, strlen( self::NS ) + 1 );
		$mapa  = self::mapa();
		if ( ! isset( $mapa[ $campo ] ) ) {
			return;
		}
		list( $grupo_antigo, $antigo ) = $mapa[ $campo ];
		if ( null === $grupo_antigo ) {
			$grupo_antigo = in_array( $grupo, array( 'billing', 'shipping' ), true ) ? $grupo : 'billing';
		}
		$valor = is_scalar( $valor ) ? (string) $valor : '';

		if ( 'company' === $antigo ) {
			if ( '' !== $valor ) {
				$objeto->set_billing_company( $valor );
			}
			return;
		}
		if ( 'birthdate' === $antigo ) {
			$valor = Documentos::data_br( $valor );
		}
		Plugin::gravar( $objeto, $grupo_antigo, $antigo, $valor );

		// Loja que atende um tipo só: grava o tipo junto com o documento.
		if ( ! Opcoes::usa_seletor() && in_array( $antigo, array( 'cpf', 'cnpj' ), true ) && '' !== $valor ) {
			Plugin::gravar( $objeto, 'billing', 'persontype', 'cpf' === $antigo ? '1' : '2' );
		}
	}

	/**
	 * Garante o tipo de pessoa no pedido quando a loja atende um tipo só.
	 *
	 * @param \WC_Order        $pedido     Pedido.
	 * @param \WP_REST_Request $requisicao Requisição.
	 */
	public static function completar_pedido( $pedido, $requisicao ) {
		unset( $requisicao );
		if ( ! Opcoes::usa_seletor() && '' === Plugin::ler( $pedido, 'billing', 'persontype' ) ) {
			Plugin::gravar( $pedido, 'billing', 'persontype', Opcoes::usa_cpf() ? '1' : '2' );
		}
	}

	/**
	 * Quando o campo adicional ainda não tem valor, usa o da chave antiga. Assim, pedidos
	 * e clientes de antes (inclusive do plugin antigo) aparecem preenchidos.
	 *
	 * @param mixed    $valor  Valor (null).
	 * @param string   $grupo  billing, shipping ou other.
	 * @param \WC_Data $objeto Pedido ou cliente.
	 * @return mixed
	 */
	public static function valor_padrao( $valor, $grupo, $objeto ) {
		if ( null !== $valor && '' !== $valor ) {
			return $valor;
		}
		if ( ! ( $objeto instanceof \WC_Order || $objeto instanceof \WC_Customer ) ) {
			return $valor;
		}
		$filtro = current_filter();
		$campo  = substr( $filtro, strlen( 'woocommerce_get_default_value_for_' . self::NS . '/' ) );
		$mapa   = self::mapa();
		if ( ! isset( $mapa[ $campo ] ) ) {
			return $valor;
		}
		list( $grupo_antigo, $antigo ) = $mapa[ $campo ];
		if ( null === $grupo_antigo ) {
			$grupo_antigo = in_array( $grupo, array( 'billing', 'shipping' ), true ) ? $grupo : 'billing';
		}
		if ( 'company' === $antigo ) {
			$lido = (string) $objeto->get_billing_company();
		} else {
			$lido = Plugin::ler( $objeto, $grupo_antigo, $antigo );
		}
		if ( 'birthdate' === $antigo ) {
			$lido = Documentos::data_iso( $lido );
		}
		return '' === $lido ? $valor : $lido;
	}

	/**
	 * Apaga as cópias do checkout em blocos de um pedido ou cliente (não salva o objeto).
	 * Usado quando a chave antiga é alterada por outro caminho.
	 *
	 * @param \WC_Data $objeto Pedido ou cliente.
	 */
	public static function descartar_copias( $objeto ) {
		foreach ( array_keys( self::mapa() ) as $campo ) {
			foreach ( array( '_wc_other/', '_wc_billing/', '_wc_shipping/' ) as $prefixo ) {
				$objeto->delete_meta_data( $prefixo . self::NS . '/' . $campo );
			}
		}
	}
}
