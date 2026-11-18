<?php
/**
 * Painel: configuração, tela do pedido e perfil do cliente.
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telas do painel.
 */
class Painel {

	/**
	 * Configuração e link na lista de plugins (sempre, mesmo com o plugin antigo ativo).
	 */
	public static function iniciar_configuracoes() {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'pagina_configuracao' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CADCERTO_ARQUIVO ), array( __CLASS__, 'links' ) );
	}

	/**
	 * Campos no pedido e no perfil do cliente.
	 */
	public static function iniciar() {
		add_filter( 'woocommerce_admin_billing_fields', array( __CLASS__, 'pedido_cobranca' ), 30, 3 );
		add_filter( 'woocommerce_admin_shipping_fields', array( __CLASS__, 'pedido_entrega' ), 30, 3 );
		add_filter( 'woocommerce_customer_meta_fields', array( __CLASS__, 'perfil' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'perfil_salvo' ), 20 );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'perfil_salvo' ), 20 );
		add_filter( 'woocommerce_ajax_get_customer_details', array( __CLASS__, 'dados_do_cliente' ), 10, 2 );
	}

	/**
	 * Registra a aba de configuração.
	 *
	 * @param array $paginas Páginas.
	 * @return array
	 */
	public static function pagina_configuracao( $paginas ) {
		require_once CADCERTO_DIR . 'includes/class-configuracoes.php';
		$paginas[] = new Configuracoes();
		return $paginas;
	}

	/**
	 * Link "Configurações" na lista de plugins.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=cadastro-certo' ) ) . '">' . esc_html__( 'Configurações', 'cadastro-certo-cpf-cnpj' ) . '</a>' );
		return $links;
	}

	/**
	 * Tira da lista os campos do checkout em blocos deste plugin (o WooCommerce os acrescenta
	 * sozinho): aqui eles aparecem pelas chaves antigas, que valem para os dois checkouts.
	 *
	 * @param array $campos Campos.
	 * @return array
	 */
	protected static function sem_copias( $campos ) {
		foreach ( array_keys( $campos ) as $chave ) {
			if ( false !== strpos( (string) $chave, Campos_Blocos::NS . '/' ) ) {
				unset( $campos[ $chave ] );
			}
		}
		return $campos;
	}

	/**
	 * Insere campos depois de uma chave.
	 *
	 * @param array  $campos Campos.
	 * @param string $depois Chave de referência.
	 * @param array  $novos  Novos campos.
	 * @return array
	 */
	protected static function inserir_depois( $campos, $depois, $novos ) {
		$saida = array();
		$feito = false;
		foreach ( $campos as $chave => $campo ) {
			$saida[ $chave ] = $campo;
			if ( $chave === $depois ) {
				$saida = array_merge( $saida, $novos );
				$feito = true;
			}
		}
		return $feito ? $saida : array_merge( $saida, $novos );
	}

	/**
	 * Ao salvar pela tela do pedido, grava a chave antiga e descarta a cópia do checkout em blocos.
	 *
	 * @param string    $id     Chave (ex.: _billing_cpf).
	 * @param string    $valor  Valor.
	 * @param \WC_Order $pedido Pedido.
	 */
	public static function salvar_campo_pedido( $id, $valor, $pedido ) {
		$valor = (string) $valor;
		if ( '_billing_cpf' === $id ) {
			$valor = Documentos::formatar_cpf( $valor );
		} elseif ( '_billing_cnpj' === $id ) {
			$valor = Documentos::formatar_cnpj( $valor );
		} elseif ( '_billing_ie' === $id ) {
			$valor = Documentos::normalizar_ie( $valor );
		}
		$pedido->update_meta_data( $id, $valor );
		Campos_Blocos::descartar_copias( $pedido );
	}

	/**
	 * Campos de cobrança na tela do pedido.
	 *
	 * @param array          $campos  Campos.
	 * @param \WC_Order|null $pedido  Pedido.
	 * @param string         $contexto Contexto.
	 * @return array
	 */
	public static function pedido_cobranca( $campos, $pedido = null, $contexto = 'edit' ) {
		$campos = self::sem_copias( $campos );
		$salvar = array( __CLASS__, 'salvar_campo_pedido' );

		$docs = array();
		$tipo = $pedido instanceof \WC_Order ? Plugin::ler( $pedido, 'billing', 'persontype' ) : '';
		if ( Opcoes::usa_seletor() || '' !== $tipo ) {
			$docs['persontype'] = array(
				'label'           => __( 'Tipo de pessoa', 'cadastro-certo-cpf-cnpj' ),
				'type'            => 'select',
				'class'           => 'js_field-persontype select short',
				'options'         => array(
					''  => __( 'Não informado', 'cadastro-certo-cpf-cnpj' ),
					'1' => __( 'Pessoa física', 'cadastro-certo-cpf-cnpj' ),
					'2' => __( 'Pessoa jurídica', 'cadastro-certo-cpf-cnpj' ),
				),
				'show'            => false,
				'update_callback' => $salvar,
			);
			// Na visualização, mostra o nome do tipo em vez do código gravado (1 ou 2).
			if ( 'view' === $contexto && '' !== $tipo ) {
				$docs['persontype']['show']  = true;
				$docs['persontype']['value'] = '2' === $tipo ? __( 'Pessoa jurídica', 'cadastro-certo-cpf-cnpj' ) : __( 'Pessoa física', 'cadastro-certo-cpf-cnpj' );
			}
		}
		$docs['cpf']  = array(
			'label'           => __( 'CPF', 'cadastro-certo-cpf-cnpj' ),
			'show'            => true,
			'update_callback' => $salvar,
		);
		$docs['cnpj'] = array(
			'label'           => __( 'CNPJ', 'cadastro-certo-cpf-cnpj' ),
			'show'            => true,
			'update_callback' => $salvar,
		);
		$docs['ie']   = array(
			'label'           => __( 'Inscrição estadual', 'cadastro-certo-cpf-cnpj' ),
			'show'            => true,
			'update_callback' => $salvar,
		);
		// RG só existia no plugin antigo; mostra se o pedido tiver.
		if ( $pedido instanceof \WC_Order && '' !== Plugin::ler( $pedido, 'billing', 'rg' ) ) {
			$docs['rg'] = array(
				'label'           => __( 'RG', 'cadastro-certo-cpf-cnpj' ),
				'show'            => true,
				'update_callback' => $salvar,
			);
		}
		if ( Opcoes::ligado( 'nascimento' ) || ( $pedido instanceof \WC_Order && '' !== Plugin::ler( $pedido, 'billing', 'birthdate' ) ) ) {
			$docs['birthdate'] = array(
				'label'           => __( 'Data de nascimento', 'cadastro-certo-cpf-cnpj' ),
				'show'            => true,
				'update_callback' => $salvar,
			);
		}
		if ( Opcoes::ligado( 'sexo' ) || ( $pedido instanceof \WC_Order && '' !== Plugin::ler( $pedido, 'billing', 'gender' ) ) ) {
			$docs['gender'] = array(
				'label'           => __( 'Sexo', 'cadastro-certo-cpf-cnpj' ),
				'show'            => true,
				'update_callback' => $salvar,
			);
		}

		$campos = self::inserir_depois( $campos, 'company', $docs );
		$campos = self::inserir_depois(
			$campos,
			'address_1',
			array(
				'number' => array(
					'label'           => __( 'Número', 'cadastro-certo-cpf-cnpj' ),
					'show'            => false,
					'update_callback' => $salvar,
				),
			)
		);
		$campos = self::inserir_depois(
			$campos,
			'address_2',
			array(
				'neighborhood' => array(
					'label'           => __( 'Bairro', 'cadastro-certo-cpf-cnpj' ),
					'show'            => false,
					'update_callback' => $salvar,
				),
			)
		);
		if ( 'nao' !== Opcoes::get( 'celular' ) || ( $pedido instanceof \WC_Order && '' !== Plugin::ler( $pedido, 'billing', 'cellphone' ) ) ) {
			$campos = self::inserir_depois(
				$campos,
				'phone',
				array(
					'cellphone' => array(
						'label'           => __( 'Celular', 'cadastro-certo-cpf-cnpj' ),
						'show'            => true,
						'update_callback' => $salvar,
					),
				)
			);
		}
		return $campos;
	}

	/**
	 * Campos de entrega na tela do pedido.
	 *
	 * @param array          $campos   Campos.
	 * @param \WC_Order|null $pedido   Pedido.
	 * @param string         $contexto Contexto.
	 * @return array
	 */
	public static function pedido_entrega( $campos, $pedido = null, $contexto = 'edit' ) {
		unset( $pedido, $contexto );
		$campos = self::sem_copias( $campos );
		$salvar = array( __CLASS__, 'salvar_campo_pedido' );
		$campos = self::inserir_depois(
			$campos,
			'address_1',
			array(
				'number' => array(
					'label'           => __( 'Número', 'cadastro-certo-cpf-cnpj' ),
					'show'            => false,
					'update_callback' => $salvar,
				),
			)
		);
		return self::inserir_depois(
			$campos,
			'address_2',
			array(
				'neighborhood' => array(
					'label'           => __( 'Bairro', 'cadastro-certo-cpf-cnpj' ),
					'show'            => false,
					'update_callback' => $salvar,
				),
			)
		);
	}

	/**
	 * Campos no perfil do usuário (Usuários > Editar).
	 *
	 * @param array $grupos Grupos de campos.
	 * @return array
	 */
	public static function perfil( $grupos ) {
		if ( isset( $grupos['billing']['fields'] ) ) {
			$c    = $grupos['billing']['fields'];
			$docs = array(
				'billing_persontype' => array(
					'label'       => __( 'Tipo de pessoa', 'cadastro-certo-cpf-cnpj' ),
					'type'        => 'select',
					'options'     => array(
						''  => __( 'Não informado', 'cadastro-certo-cpf-cnpj' ),
						'1' => __( 'Pessoa física', 'cadastro-certo-cpf-cnpj' ),
						'2' => __( 'Pessoa jurídica', 'cadastro-certo-cpf-cnpj' ),
					),
					'description' => '',
				),
				'billing_cpf'        => array(
					'label'       => __( 'CPF', 'cadastro-certo-cpf-cnpj' ),
					'description' => '',
				),
				'billing_cnpj'       => array(
					'label'       => __( 'CNPJ', 'cadastro-certo-cpf-cnpj' ),
					'description' => '',
				),
				'billing_ie'         => array(
					'label'       => __( 'Inscrição estadual', 'cadastro-certo-cpf-cnpj' ),
					'description' => '',
				),
			);
			if ( Opcoes::ligado( 'nascimento' ) ) {
				$docs['billing_birthdate'] = array(
					'label'       => __( 'Data de nascimento', 'cadastro-certo-cpf-cnpj' ),
					'description' => 'dd/mm/aaaa',
				);
			}
			if ( Opcoes::ligado( 'sexo' ) ) {
				$docs['billing_gender'] = array(
					'label'       => __( 'Sexo', 'cadastro-certo-cpf-cnpj' ),
					'description' => '',
				);
			}
			$c = self::inserir_depois( $c, 'billing_company', $docs );
			$c = self::inserir_depois(
				$c,
				'billing_address_1',
				array(
					'billing_number' => array(
						'label'       => __( 'Número', 'cadastro-certo-cpf-cnpj' ),
						'description' => '',
					),
				)
			);
			$c = self::inserir_depois(
				$c,
				'billing_address_2',
				array(
					'billing_neighborhood' => array(
						'label'       => __( 'Bairro', 'cadastro-certo-cpf-cnpj' ),
						'description' => '',
					),
				)
			);
			if ( 'nao' !== Opcoes::get( 'celular' ) ) {
				$c = self::inserir_depois(
					$c,
					'billing_phone',
					array(
						'billing_cellphone' => array(
							'label'       => __( 'Celular', 'cadastro-certo-cpf-cnpj' ),
							'description' => '',
						),
					)
				);
			}
			$grupos['billing']['fields'] = $c;
		}
		if ( isset( $grupos['shipping']['fields'] ) ) {
			$c = self::inserir_depois(
				$grupos['shipping']['fields'],
				'shipping_address_1',
				array(
					'shipping_number' => array(
						'label'       => __( 'Número', 'cadastro-certo-cpf-cnpj' ),
						'description' => '',
					),
				)
			);
			$c = self::inserir_depois(
				$c,
				'shipping_address_2',
				array(
					'shipping_neighborhood' => array(
						'label'       => __( 'Bairro', 'cadastro-certo-cpf-cnpj' ),
						'description' => '',
					),
				)
			);
			$grupos['shipping']['fields'] = $c;
		}
		return $grupos;
	}

	/**
	 * Depois que o WooCommerce salva o perfil, descarta as cópias do checkout em blocos.
	 *
	 * @param int $usuario_id Usuário.
	 */
	public static function perfil_salvo( $usuario_id ) {
		if ( ! current_user_can( 'edit_user', $usuario_id ) ) {
			return;
		}
		$cliente = new \WC_Customer( $usuario_id );
		Campos_Blocos::descartar_copias( $cliente );
		$cliente->save_meta_data();
	}

	/**
	 * Ao escolher o cliente num pedido novo pelo painel, preenche também os campos brasileiros.
	 *
	 * @param array        $dados   Dados.
	 * @param \WC_Customer $cliente Cliente.
	 * @return array
	 */
	public static function dados_do_cliente( $dados, $cliente ) {
		foreach ( Plugin::CHAVES_COBRANCA as $campo ) {
			$dados['billing'][ $campo ] = Plugin::ler( $cliente, 'billing', $campo );
		}
		foreach ( array( 'number', 'neighborhood' ) as $campo ) {
			$dados['shipping'][ $campo ] = Plugin::ler( $cliente, 'shipping', $campo );
		}
		return $dados;
	}
}
