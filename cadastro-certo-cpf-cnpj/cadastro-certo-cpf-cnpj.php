<?php
/**
 * Plugin Name:          Cadastro Certo – CPF e CNPJ Alfanumérico for WooCommerce
 * Plugin URI:           https://www.deltatech.network/plugins/
 * Description:          Campos brasileiros no checkout clássico e no checkout em blocos: tipo de pessoa, CPF, CNPJ (numérico e alfanumérico), inscrição estadual, número e bairro. Usa os mesmos dados do Brazilian Market on WooCommerce.
 * Version:              1.0.0
 * Requires at least:    6.7
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Deltatech
 * Author URI:           https://www.deltatech.network/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          cadastro-certo-cpf-cnpj
 * Domain Path:          /languages
 * WC requires at least: 9.9
 * WC tested up to:      11.3
 *
 * @package CadastroCerto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CADCERTO_VERSAO', '1.0.0' );
define( 'CADCERTO_ARQUIVO', __FILE__ );
define( 'CADCERTO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CADCERTO_URL', plugin_dir_url( __FILE__ ) );

require_once CADCERTO_DIR . 'includes/class-documentos.php';
require_once CADCERTO_DIR . 'includes/class-opcoes.php';
require_once CADCERTO_DIR . 'includes/class-plugin-antigo.php';
require_once CADCERTO_DIR . 'includes/class-campos-classicos.php';
require_once CADCERTO_DIR . 'includes/class-campos-blocos.php';
require_once CADCERTO_DIR . 'includes/class-endereco.php';
require_once CADCERTO_DIR . 'includes/class-painel.php';
require_once CADCERTO_DIR . 'includes/class-api.php';
require_once CADCERTO_DIR . 'includes/class-plugin.php';

// Compatibilidade declarada: pedidos em tabelas próprias (HPOS) e checkout em blocos.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CADCERTO_ARQUIVO, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CADCERTO_ARQUIVO, true );
		}
	}
);

register_activation_hook( CADCERTO_ARQUIVO, array( \Deltatech\CadastroCerto\Opcoes::class, 'importar_do_antigo' ) );

add_action( 'plugins_loaded', array( \Deltatech\CadastroCerto\Plugin::class, 'iniciar' ), 20 );
