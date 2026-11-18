<?php
/**
 * Convivência com o Brazilian Market on WooCommerce.
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detecta o plugin antigo, avisa no painel e oferece assumir o lugar dele.
 */
class Plugin_Antigo {

	const BASENAME = 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php';

	/**
	 * O plugin antigo está carregado?
	 *
	 * @return bool
	 */
	public static function ativo() {
		return class_exists( 'Extra_Checkout_Fields_For_Brazil' ) || defined( 'CSBMW_PLUGIN_FILE' );
	}

	/**
	 * Liga o aviso e a ação de assumir.
	 */
	public static function iniciar() {
		add_action( 'admin_notices', array( __CLASS__, 'aviso' ) );
		add_action( 'admin_post_cadcerto_assumir', array( __CLASS__, 'assumir' ) );
	}

	/**
	 * Mostra o aviso só na tela de plugins e nas configurações do WooCommerce.
	 */
	public static function aviso() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$tela = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $tela || ! in_array( $tela->id, array( 'plugins', 'woocommerce_page_wc-settings' ), true ) ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=cadcerto_assumir' ), 'cadcerto_assumir' );
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Cadastro Certo em espera.', 'cadastro-certo-cpf-cnpj' ) . '</strong> ';
		echo esc_html__( 'O Brazilian Market on WooCommerce está ativo. Para não aparecerem campos repetidos no checkout, o Cadastro Certo não mostra os campos dele enquanto o outro estiver ligado.', 'cadastro-certo-cpf-cnpj' ) . '</p>';
		echo '<p>' . esc_html__( 'Ao assumir, o Cadastro Certo copia a configuração do plugin antigo e o desativa. Pedidos e clientes continuam com os mesmos dados (CPF, CNPJ, número, bairro): nada é apagado.', 'cadastro-certo-cpf-cnpj' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Assumir os campos e desativar o plugin antigo', 'cadastro-certo-cpf-cnpj' ) . '</a></p></div>';
	}

	/**
	 * Copia a configuração e desativa o plugin antigo.
	 */
	public static function assumir() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para fazer isso.', 'cadastro-certo-cpf-cnpj' ) );
		}
		check_admin_referer( 'cadcerto_assumir' );

		Opcoes::importar_do_antigo();
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( self::BASENAME );

		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=cadastro-certo&assumido=1' ) );
		exit;
	}
}
