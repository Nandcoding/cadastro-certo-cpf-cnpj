<?php
/**
 * Opções do plugin.
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lê as opções com valores padrão e importa a configuração do plugin antigo.
 */
class Opcoes {

	/**
	 * Valores padrão.
	 *
	 * @return array
	 */
	public static function padroes() {
		return array(
			'tipo_pessoa'        => 'ambos',
			'validar_cpf'        => 'yes',
			'validar_cnpj'       => 'yes',
			'ie'                 => 'opcional',
			'bairro_obrigatorio' => 'yes',
			'celular'            => 'nao',
			'nascimento'         => 'no',
			'sexo'               => 'no',
			'somente_brasil'     => 'yes',
			'mascaras'           => 'yes',
		);
	}

	/**
	 * Valor de uma opção.
	 *
	 * @param string $nome Nome sem prefixo.
	 * @return string
	 */
	public static function get( $nome ) {
		$padroes = self::padroes();
		$padrao  = isset( $padroes[ $nome ] ) ? $padroes[ $nome ] : '';
		$valor   = get_option( 'cadcerto_' . $nome, $padrao );
		return is_string( $valor ) ? $valor : $padrao;
	}

	/**
	 * Opção do tipo sim/não.
	 *
	 * @param string $nome Nome sem prefixo.
	 * @return bool
	 */
	public static function ligado( $nome ) {
		return 'yes' === self::get( $nome );
	}

	/**
	 * Pede pessoa física?
	 *
	 * @return bool
	 */
	public static function usa_cpf() {
		return in_array( self::get( 'tipo_pessoa' ), array( 'ambos', 'fisica' ), true );
	}

	/**
	 * Pede pessoa jurídica?
	 *
	 * @return bool
	 */
	public static function usa_cnpj() {
		return in_array( self::get( 'tipo_pessoa' ), array( 'ambos', 'juridica' ), true );
	}

	/**
	 * Mostra o seletor de tipo de pessoa?
	 *
	 * @return bool
	 */
	public static function usa_seletor() {
		return 'ambos' === self::get( 'tipo_pessoa' );
	}

	/**
	 * Copia a configuração do Brazilian Market on WooCommerce, se existir e se este plugin
	 * ainda não tiver configuração própria.
	 *
	 * @return bool True se importou.
	 */
	public static function importar_do_antigo() {
		if ( false !== get_option( 'cadcerto_tipo_pessoa', false ) ) {
			return false;
		}
		$antigo = get_option( 'wcbcf_settings' );
		if ( ! is_array( $antigo ) ) {
			return false;
		}

		$tipo  = isset( $antigo['person_type'] ) ? (int) $antigo['person_type'] : 1;
		$mapa  = array(
			1 => 'ambos',
			2 => 'fisica',
			3 => 'juridica',
		);
		$cel   = isset( $antigo['cell_phone'] ) ? (string) $antigo['cell_phone'] : '0';
		$novas = array(
			'tipo_pessoa'        => isset( $mapa[ $tipo ] ) ? $mapa[ $tipo ] : 'ambos',
			'validar_cpf'        => isset( $antigo['validate_cpf'] ) ? 'yes' : 'no',
			'validar_cnpj'       => isset( $antigo['validate_cnpj'] ) ? 'yes' : 'no',
			// No plugin antigo, ligar a IE a tornava obrigatória.
			'ie'                 => isset( $antigo['ie'] ) ? 'obrigatoria' : 'opcional',
			'bairro_obrigatorio' => ( isset( $antigo['neighborhood_required'] ) && '1' === (string) $antigo['neighborhood_required'] ) ? 'yes' : 'no',
			'celular'            => ( '2' === $cel ) ? 'obrigatorio' : ( ( '1' === $cel ) ? 'opcional' : 'nao' ),
			'nascimento'         => isset( $antigo['birthdate'] ) ? 'yes' : 'no',
			'sexo'               => isset( $antigo['gender'] ) ? 'yes' : 'no',
			'somente_brasil'     => isset( $antigo['only_brazil'] ) ? 'yes' : 'no',
			'mascaras'           => isset( $antigo['maskedinput'] ) ? 'yes' : 'no',
		);
		foreach ( $novas as $nome => $valor ) {
			update_option( 'cadcerto_' . $nome, $valor );
		}
		update_option( 'cadcerto_importado_do_antigo', gmdate( 'Y-m-d H:i:s' ), false );
		return true;
	}
}
