<?php
/**
 * Carregamento do plugin.
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liga as partes do plugin.
 */
class Plugin {

	/**
	 * Chaves usadas pelo Brazilian Market on WooCommerce, mantidas para não quebrar
	 * gateways, emissores de nota e ERPs. No pedido levam "_" na frente; no cliente, não.
	 */
	const CHAVES_COBRANCA = array( 'persontype', 'cpf', 'rg', 'cnpj', 'ie', 'birthdate', 'gender', 'number', 'neighborhood', 'cellphone' );

	/**
	 * Inicia.
	 */
	public static function iniciar() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'aviso_sem_woocommerce' ) );
			return;
		}

		Painel::iniciar_configuracoes();

		// Com o plugin antigo ativo, este fica em espera para não repetir campos.
		if ( Plugin_Antigo::ativo() ) {
			Plugin_Antigo::iniciar();
			return;
		}

		Campos_Classicos::iniciar();
		Campos_Blocos::iniciar();
		Endereco::iniciar();
		Painel::iniciar();
		Api::iniciar();
	}

	/**
	 * Aviso quando o WooCommerce não está ativo.
	 */
	public static function aviso_sem_woocommerce() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'O Cadastro Certo precisa do WooCommerce ativo para funcionar.', 'cadastro-certo-cpf-cnpj' ) . '</p></div>';
	}

	/**
	 * Gêneros, com os mesmos textos gravados pelo plugin antigo em português.
	 *
	 * @return array
	 */
	public static function generos() {
		return array(
			'Feminino'          => __( 'Feminino', 'cadastro-certo-cpf-cnpj' ),
			'Masculino'         => __( 'Masculino', 'cadastro-certo-cpf-cnpj' ),
			'Outro'             => __( 'Outro', 'cadastro-certo-cpf-cnpj' ),
			'Prefiro não dizer' => __( 'Prefiro não dizer', 'cadastro-certo-cpf-cnpj' ),
		);
	}

	/**
	 * Lê um dado brasileiro de um pedido ou cliente, pela chave antiga.
	 *
	 * @param \WC_Data $objeto Pedido ou cliente.
	 * @param string   $grupo  billing ou shipping.
	 * @param string   $campo  Ex.: cpf, number.
	 * @return string
	 */
	public static function ler( $objeto, $grupo, $campo ) {
		$chave = $grupo . '_' . $campo;
		if ( $objeto instanceof \WC_Order ) {
			$chave = '_' . $chave;
		}
		$valor = $objeto->get_meta( $chave, true );
		return is_scalar( $valor ) ? (string) $valor : '';
	}

	/**
	 * Grava um dado brasileiro num pedido ou cliente, pela chave antiga (não salva o objeto).
	 *
	 * @param \WC_Data $objeto Pedido ou cliente.
	 * @param string   $grupo  billing ou shipping.
	 * @param string   $campo  Ex.: cpf, number.
	 * @param string   $valor  Valor.
	 */
	public static function gravar( $objeto, $grupo, $campo, $valor ) {
		$chave = $grupo . '_' . $campo;
		if ( $objeto instanceof \WC_Order ) {
			$chave = '_' . $chave;
		}
		$objeto->update_meta_data( $chave, $valor );
	}
}
