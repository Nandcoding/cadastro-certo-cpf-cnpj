<?php
/**
 * Campos no checkout clássico (shortcode [woocommerce_checkout]).
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campos, validação e gravação no checkout clássico.
 *
 * Os nomes dos campos (billing_cpf, billing_number…) são os do plugin antigo; o próprio
 * WooCommerce grava cada um como "_billing_cpf" no pedido e "billing_cpf" no cliente.
 */
class Campos_Classicos {

	/**
	 * Liga os ganchos.
	 */
	public static function iniciar() {
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'campos_cobranca' ), 20 );
		add_filter( 'woocommerce_shipping_fields', array( __CLASS__, 'campos_entrega' ), 20 );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'local_brasil' ), 20 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'normalizar_postado' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validar' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'completar_pedido' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_customer', array( __CLASS__, 'completar_cliente' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ) );
	}

	/**
	 * Os campos clássicos entram só no checkout clássico. Em "Minha conta", o próprio
	 * WooCommerce mostra os campos registrados para o checkout em blocos (ver Campos_Blocos),
	 * que gravam nas mesmas chaves; assim nada aparece repetido.
	 *
	 * @return bool
	 */
	protected static function contexto_classico() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return false;
		}
		return true;
	}

	/**
	 * Campos de cobrança.
	 *
	 * @param array $campos Campos do WooCommerce.
	 * @return array
	 */
	public static function campos_cobranca( $campos ) {
		if ( ! self::contexto_classico() ) {
			return $campos;
		}

		if ( Opcoes::usa_seletor() ) {
			$campos['billing_persontype'] = array(
				'type'     => 'select',
				'label'    => __( 'Tipo de pessoa', 'cadastro-certo-cpf-cnpj' ),
				'class'    => array( 'form-row-wide', 'cadcerto-tipo-pessoa' ),
				'required' => true,
				'options'  => array(
					''  => __( 'Selecione', 'cadastro-certo-cpf-cnpj' ),
					'1' => __( 'Pessoa física', 'cadastro-certo-cpf-cnpj' ),
					'2' => __( 'Pessoa jurídica', 'cadastro-certo-cpf-cnpj' ),
				),
				'priority' => 22,
			);
		}

		if ( Opcoes::usa_cpf() ) {
			$campos['billing_cpf'] = array(
				'type'              => 'text',
				'label'             => __( 'CPF', 'cadastro-certo-cpf-cnpj' ),
				'placeholder'       => '000.000.000-00',
				'class'             => array( 'form-row-wide', 'cadcerto-pf' ),
				'required'          => true,
				'priority'          => 23,
				'custom_attributes' => array(
					'inputmode' => 'numeric',
					'maxlength' => '14',
				),
			);
		}

		if ( Opcoes::usa_cnpj() ) {
			// O WooCommerce pode ter escondido o campo de empresa (configuração padrão das lojas
			// novas); para pessoa jurídica ele volta como "Razão social".
			if ( ! isset( $campos['billing_company'] ) ) {
				$campos['billing_company'] = array(
					'type'         => 'text',
					'autocomplete' => 'organization',
				);
			}
			$campos['billing_company']['label']    = __( 'Razão social', 'cadastro-certo-cpf-cnpj' );
			$campos['billing_company']['required'] = true;
			$campos['billing_company']['class']    = array( 'form-row-wide', 'cadcerto-pj' );
			$campos['billing_company']['priority'] = 25;
			$campos['billing_cnpj'] = array(
				'type'              => 'text',
				'label'             => __( 'CNPJ', 'cadastro-certo-cpf-cnpj' ),
				'placeholder'       => '00.000.000/0000-00',
				'description'       => __( 'Aceita o CNPJ só com números e o novo, com letras.', 'cadastro-certo-cpf-cnpj' ),
				'class'             => array( 'form-row-wide', 'cadcerto-pj' ),
				'required'          => true,
				'priority'          => 26,
				'custom_attributes' => array(
					'autocapitalize' => 'characters',
					'maxlength'      => '18',
				),
			);
			if ( 'nao' !== Opcoes::get( 'ie' ) ) {
				$campos['billing_ie'] = array(
					'type'              => 'text',
					'label'             => __( 'Inscrição estadual', 'cadastro-certo-cpf-cnpj' ),
					'placeholder'       => __( 'Número ou ISENTO', 'cadastro-certo-cpf-cnpj' ),
					'class'             => array( 'form-row-wide', 'cadcerto-pj' ),
					'required'          => 'obrigatoria' === Opcoes::get( 'ie' ),
					'priority'          => 27,
					'custom_attributes' => array( 'autocapitalize' => 'characters' ),
				);
			}
		} elseif ( isset( $campos['billing_company'] ) && 'fisica' === Opcoes::get( 'tipo_pessoa' ) ) {
			unset( $campos['billing_company'] );
		}

		if ( Opcoes::ligado( 'nascimento' ) ) {
			$campos['billing_birthdate'] = array(
				'type'              => 'text',
				'label'             => __( 'Data de nascimento', 'cadastro-certo-cpf-cnpj' ),
				'placeholder'       => 'dd/mm/aaaa',
				'class'             => array( 'form-row-first' ),
				'required'          => true,
				'priority'          => 31,
				'custom_attributes' => array(
					'inputmode' => 'numeric',
					'maxlength' => '10',
				),
			);
		}
		if ( Opcoes::ligado( 'sexo' ) ) {
			$campos['billing_gender'] = array(
				'type'     => 'select',
				'label'    => __( 'Sexo', 'cadastro-certo-cpf-cnpj' ),
				'class'    => array( 'form-row-last' ),
				'required' => true,
				'options'  => array_merge( array( '' => __( 'Selecione', 'cadastro-certo-cpf-cnpj' ) ), Plugin::generos() ),
				'priority' => 32,
			);
		}

		$campos = self::campos_endereco( $campos, 'billing' );

		if ( 'nao' !== Opcoes::get( 'celular' ) ) {
			$campos['billing_cellphone'] = array(
				'type'     => 'tel',
				'label'    => __( 'Celular', 'cadastro-certo-cpf-cnpj' ),
				'class'    => array( 'form-row-wide' ),
				'required' => 'obrigatorio' === Opcoes::get( 'celular' ),
				'validate' => array( 'phone' ),
				'priority' => 105,
			);
		}

		return $campos;
	}

	/**
	 * Campos de entrega.
	 *
	 * @param array $campos Campos do WooCommerce.
	 * @return array
	 */
	public static function campos_entrega( $campos ) {
		if ( ! self::contexto_classico() ) {
			return $campos;
		}
		return self::campos_endereco( $campos, 'shipping' );
	}

	/**
	 * Número e bairro.
	 *
	 * @param array  $campos Campos.
	 * @param string $grupo  billing ou shipping.
	 * @return array
	 */
	protected static function campos_endereco( $campos, $grupo ) {
		$campos[ $grupo . '_number' ]       = array(
			'type'     => 'text',
			'label'    => __( 'Número', 'cadastro-certo-cpf-cnpj' ),
			'class'    => array( 'form-row-wide', 'address-field' ),
			'required' => true,
			'priority' => 55,
		);
		$campos[ $grupo . '_neighborhood' ] = array(
			'type'     => 'text',
			'label'    => __( 'Bairro', 'cadastro-certo-cpf-cnpj' ),
			'class'    => array( 'form-row-wide', 'address-field' ),
			'required' => Opcoes::ligado( 'bairro_obrigatorio' ),
			'priority' => 65,
		);
		// Ordem: endereço, número, complemento (com rótulo visível), bairro.
		if ( isset( $campos[ $grupo . '_address_2' ] ) ) {
			$campos[ $grupo . '_address_2' ]['priority']    = 60;
			$campos[ $grupo . '_address_2' ]['label']       = __( 'Complemento', 'cadastro-certo-cpf-cnpj' );
			$campos[ $grupo . '_address_2' ]['label_class'] = array();
		}
		return $campos;
	}

	/**
	 * Ordem e rótulos dos campos de endereço no Brasil: CEP primeiro, complemento com nome claro.
	 *
	 * @param array $locais Configuração por país.
	 * @return array
	 */
	public static function local_brasil( $locais ) {
		if ( ! isset( $locais['BR'] ) || ! is_array( $locais['BR'] ) ) {
			$locais['BR'] = array();
		}
		$locais['BR']['postcode']  = array_merge( isset( $locais['BR']['postcode'] ) ? (array) $locais['BR']['postcode'] : array(), array( 'priority' => 45 ) );
		// O número tem campo próprio.
		$locais['BR']['address_1'] = array_merge(
			isset( $locais['BR']['address_1'] ) ? (array) $locais['BR']['address_1'] : array(),
			array( 'placeholder' => __( 'Rua, avenida…', 'cadastro-certo-cpf-cnpj' ) )
		);
		$locais['BR']['address_2'] = array_merge(
			isset( $locais['BR']['address_2'] ) ? (array) $locais['BR']['address_2'] : array(),
			array(
				'label'       => __( 'Complemento', 'cadastro-certo-cpf-cnpj' ),
				'placeholder' => __( 'Apartamento, bloco, sala (opcional)', 'cadastro-certo-cpf-cnpj' ),
			)
		);
		return $locais;
	}

	/**
	 * O cliente está fora do Brasil e a loja só confere documentos de quem está no Brasil?
	 *
	 * @param array $dados Dados postados.
	 * @return bool
	 */
	protected static function dispensado( $dados ) {
		$pais = isset( $dados['billing_country'] ) ? (string) $dados['billing_country'] : '';
		return Opcoes::ligado( 'somente_brasil' ) && '' !== $pais && 'BR' !== $pais;
	}

	/**
	 * Tipo de pessoa efetivo: '1' (física), '2' (jurídica) ou '' (não escolhido).
	 *
	 * @param array $dados Dados postados.
	 * @return string
	 */
	protected static function tipo( $dados ) {
		if ( ! Opcoes::usa_seletor() ) {
			return Opcoes::usa_cpf() ? '1' : '2';
		}
		$tipo = isset( $dados['billing_persontype'] ) ? (string) $dados['billing_persontype'] : '';
		return in_array( $tipo, array( '1', '2' ), true ) ? $tipo : '';
	}

	/**
	 * Formata CPF, CNPJ e IE antes de validar e gravar.
	 *
	 * @param array $dados Dados postados.
	 * @return array
	 */
	public static function normalizar_postado( $dados ) {
		if ( ! empty( $dados['billing_cpf'] ) ) {
			$dados['billing_cpf'] = Documentos::formatar_cpf( $dados['billing_cpf'] );
		}
		if ( ! empty( $dados['billing_cnpj'] ) ) {
			$dados['billing_cnpj'] = Documentos::formatar_cnpj( $dados['billing_cnpj'] );
		}
		if ( ! empty( $dados['billing_ie'] ) ) {
			$dados['billing_ie'] = Documentos::normalizar_ie( $dados['billing_ie'] );
		}
		if ( ! empty( $dados['billing_birthdate'] ) ) {
			$data = Documentos::data_br( $dados['billing_birthdate'] );
			if ( '' !== $data ) {
				$dados['billing_birthdate'] = $data;
			}
		}
		// Quem escolheu pessoa física não grava dados de empresa, e vice-versa.
		$tipo = self::tipo( $dados );
		if ( '1' === $tipo && Opcoes::usa_seletor() ) {
			if ( isset( $dados['billing_cnpj'] ) ) {
				$dados['billing_cnpj'] = '';
			}
			if ( isset( $dados['billing_ie'] ) ) {
				$dados['billing_ie'] = '';
			}
		} elseif ( '2' === $tipo && Opcoes::usa_seletor() ) {
			$dados['billing_cpf'] = '';
		}
		return $dados;
	}

	/**
	 * Valida os campos brasileiros.
	 *
	 * @param array     $dados Dados postados.
	 * @param \WP_Error $erros Erros do checkout.
	 */
	public static function validar( $dados, $erros ) {
		/**
		 * Permite desligar a validação do Cadastro Certo no checkout clássico.
		 *
		 * @param bool $desligar Padrão false.
		 */
		if ( apply_filters( 'cadcerto_desligar_validacao', false ) ) {
			return;
		}
		// Estes campos aparecem como obrigatórios, mas a obrigação depende do tipo de pessoa
		// e do país: tira os erros genéricos do WooCommerce e confere aqui.
		foreach ( array( 'billing_persontype', 'billing_cpf', 'billing_company', 'billing_cnpj', 'billing_ie' ) as $campo ) {
			$erros->remove( $campo . '_required' );
		}
		if ( self::dispensado( $dados ) ) {
			return;
		}

		$tipo = self::tipo( $dados );
		if ( '' === $tipo ) {
			$erros->add( 'billing_persontype_required', __( 'Escolha o <strong>tipo de pessoa</strong>: física ou jurídica.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_persontype' ) );
			return;
		}

		if ( '1' === $tipo ) {
			$cpf = isset( $dados['billing_cpf'] ) ? (string) $dados['billing_cpf'] : '';
			if ( '' === $cpf ) {
				$erros->add( 'billing_cpf_required', __( 'Informe o <strong>CPF</strong>.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_cpf' ) );
			} elseif ( Opcoes::ligado( 'validar_cpf' ) && ! Documentos::cpf_valido( $cpf ) ) {
				$erros->add( 'billing_cpf_validation', __( 'O <strong>CPF</strong> informado não é válido. Confira os números.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_cpf' ) );
			}
			return;
		}

		$empresa = isset( $dados['billing_company'] ) ? trim( (string) $dados['billing_company'] ) : '';
		if ( '' === $empresa ) {
			$erros->add( 'billing_company_required', __( 'Informe a <strong>razão social</strong>.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_company' ) );
		}
		$cnpj = isset( $dados['billing_cnpj'] ) ? (string) $dados['billing_cnpj'] : '';
		if ( '' === $cnpj ) {
			$erros->add( 'billing_cnpj_required', __( 'Informe o <strong>CNPJ</strong>.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_cnpj' ) );
		} elseif ( Opcoes::ligado( 'validar_cnpj' ) && ! Documentos::cnpj_valido( $cnpj ) ) {
			$erros->add( 'billing_cnpj_validation', __( 'O <strong>CNPJ</strong> informado não é válido. Confira letras e números (o CNPJ novo pode ter letras).', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_cnpj' ) );
		}
		if ( 'nao' !== Opcoes::get( 'ie' ) ) {
			$ie = isset( $dados['billing_ie'] ) ? (string) $dados['billing_ie'] : '';
			if ( '' === $ie && 'obrigatoria' === Opcoes::get( 'ie' ) ) {
				$erros->add( 'billing_ie_required', __( 'Informe a <strong>inscrição estadual</strong> ou escreva ISENTO.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_ie' ) );
			} elseif ( '' !== $ie && ! Documentos::ie_aceitavel( $ie ) ) {
				$erros->add( 'billing_ie_validation', __( 'A <strong>inscrição estadual</strong> deve ter só números (com ou sem pontos) ou a palavra ISENTO.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_ie' ) );
			}
		}
		if ( Opcoes::ligado( 'nascimento' ) && ! empty( $dados['billing_birthdate'] ) && '' === Documentos::data_br( $dados['billing_birthdate'] ) ) {
			$erros->add( 'billing_birthdate_validation', __( 'A <strong>data de nascimento</strong> deve estar no formato dd/mm/aaaa.', 'cadastro-certo-cpf-cnpj' ), array( 'id' => 'billing_birthdate' ) );
		}
	}

	/**
	 * Grava o tipo de pessoa quando a loja só atende um tipo (o campo não aparece).
	 *
	 * @param \WC_Order $pedido Pedido.
	 * @param array     $dados  Dados postados.
	 */
	public static function completar_pedido( $pedido, $dados ) {
		if ( ! Opcoes::usa_seletor() ) {
			$pedido->update_meta_data( '_billing_persontype', self::tipo( $dados ) );
		}
	}

	/**
	 * Mesmo ajuste no cliente e descarte da cópia usada pelo checkout em blocos,
	 * para que o próximo checkout em blocos leia os dados atualizados.
	 *
	 * @param \WC_Customer $cliente Cliente.
	 * @param array        $dados   Dados postados.
	 */
	public static function completar_cliente( $cliente, $dados ) {
		if ( ! Opcoes::usa_seletor() ) {
			$cliente->update_meta_data( 'billing_persontype', self::tipo( $dados ) );
		}
		Campos_Blocos::descartar_copias( $cliente );
	}

	/**
	 * Máscaras e troca de campos por tipo de pessoa.
	 */
	public static function scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_account_page() ) ) {
			return;
		}
		wp_enqueue_style( 'cadcerto', CADCERTO_URL . 'assets/css/cadastro-certo.css', array(), CADCERTO_VERSAO );
		wp_enqueue_script( 'cadcerto', CADCERTO_URL . 'assets/js/cadastro-certo.js', array(), CADCERTO_VERSAO, true );
		wp_localize_script(
			'cadcerto',
			'cadcertoConfig',
			array(
				'tipoPessoa'    => Opcoes::get( 'tipo_pessoa' ),
				'somenteBrasil' => Opcoes::ligado( 'somente_brasil' ) ? '1' : '0',
				'mascaras'      => Opcoes::ligado( 'mascaras' ) ? '1' : '0',
			)
		);
	}
}
