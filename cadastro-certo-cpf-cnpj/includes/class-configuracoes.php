<?php
/**
 * Aba "CPF e CNPJ" em WooCommerce > Configurações.
 *
 * Carregado só quando o WooCommerce monta as páginas de configuração.
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página de configuração.
 */
class Configuracoes extends \WC_Settings_Page {

	/**
	 * Monta a aba.
	 */
	public function __construct() {
		$this->id    = 'cadastro-certo';
		$this->label = __( 'CPF e CNPJ', 'cadastro-certo-cpf-cnpj' );
		parent::__construct();
	}

	/**
	 * Campos da configuração.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		$p = Opcoes::padroes();
		return array(
			array(
				'title' => __( 'Campos brasileiros no checkout', 'cadastro-certo-cpf-cnpj' ),
				'type'  => 'title',
				'desc'  => __( 'Vale para o checkout clássico e para o checkout em blocos. Os dados ficam nas mesmas chaves usadas pelo Brazilian Market on WooCommerce (_billing_cpf, _billing_cnpj, _billing_number…), que gateways, emissores de nota e ERPs já leem.', 'cadastro-certo-cpf-cnpj' ),
				'id'    => 'cadcerto_campos',
			),
			array(
				'title'   => __( 'Tipo de pessoa', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_tipo_pessoa',
				'type'    => 'select',
				'default' => $p['tipo_pessoa'],
				'options' => array(
					'ambos'    => __( 'Física e jurídica (o cliente escolhe)', 'cadastro-certo-cpf-cnpj' ),
					'fisica'   => __( 'Só pessoa física (CPF)', 'cadastro-certo-cpf-cnpj' ),
					'juridica' => __( 'Só pessoa jurídica (CNPJ)', 'cadastro-certo-cpf-cnpj' ),
				),
			),
			array(
				'title'   => __( 'Validar CPF', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Recusar CPF com dígito verificador errado', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_validar_cpf',
				'type'    => 'checkbox',
				'default' => $p['validar_cpf'],
			),
			array(
				'title'   => __( 'Validar CNPJ', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Recusar CNPJ com dígito verificador errado (aceita o CNPJ numérico e o novo, com letras)', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_validar_cnpj',
				'type'    => 'checkbox',
				'default' => $p['validar_cnpj'],
			),
			array(
				'title'   => __( 'Inscrição estadual', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Aparece só para pessoa jurídica. O cliente pode escrever ISENTO.', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_ie',
				'type'    => 'select',
				'default' => $p['ie'],
				'options' => array(
					'nao'         => __( 'Não pedir', 'cadastro-certo-cpf-cnpj' ),
					'opcional'    => __( 'Opcional', 'cadastro-certo-cpf-cnpj' ),
					'obrigatoria' => __( 'Obrigatória', 'cadastro-certo-cpf-cnpj' ),
				),
			),
			array(
				'title'   => __( 'Bairro obrigatório', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Exigir o bairro no endereço', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_bairro_obrigatorio',
				'type'    => 'checkbox',
				'default' => $p['bairro_obrigatorio'],
			),
			array(
				'title'   => __( 'Celular', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Campo separado do telefone.', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_celular',
				'type'    => 'select',
				'default' => $p['celular'],
				'options' => array(
					'nao'         => __( 'Não pedir', 'cadastro-certo-cpf-cnpj' ),
					'opcional'    => __( 'Opcional', 'cadastro-certo-cpf-cnpj' ),
					'obrigatorio' => __( 'Obrigatório', 'cadastro-certo-cpf-cnpj' ),
				),
			),
			array(
				'title'   => __( 'Data de nascimento', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Pedir a data de nascimento (obrigatória quando ligada)', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_nascimento',
				'type'    => 'checkbox',
				'default' => $p['nascimento'],
			),
			array(
				'title'   => __( 'Sexo', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Pedir o sexo (obrigatório quando ligado)', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_sexo',
				'type'    => 'checkbox',
				'default' => $p['sexo'],
			),
			array(
				'title'   => __( 'Só clientes no Brasil', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Não exigir CPF ou CNPJ quando o país de cobrança não for o Brasil', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_somente_brasil',
				'type'    => 'checkbox',
				'default' => $p['somente_brasil'],
			),
			array(
				'title'   => __( 'Máscaras', 'cadastro-certo-cpf-cnpj' ),
				'desc'    => __( 'Pontuar CPF, CNPJ, celular e datas enquanto o cliente digita', 'cadastro-certo-cpf-cnpj' ),
				'id'      => 'cadcerto_mascaras',
				'type'    => 'checkbox',
				'default' => $p['mascaras'],
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cadcerto_campos',
			),
		);
	}

	/**
	 * Mostra a página, com o recado de quando o plugin antigo foi substituído e a menção ao complemento.
	 */
	public function output() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- só exibe um recado após o redirecionamento.
		if ( isset( $_GET['assumido'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Pronto: o Cadastro Certo assumiu os campos e o Brazilian Market on WooCommerce foi desativado. Confira a configuração abaixo, copiada do plugin antigo.', 'cadastro-certo-cpf-cnpj' ) . '</p></div>';
		}
		parent::output();
		echo '<p class="cadcerto-complemento" style="margin-top:2em;color:#50575e;">';
		echo esc_html__( 'Loja de atacado ou B2B? Existe um complemento, vendido à parte, com preenchimento por CNPJ e CEP, validação da inscrição estadual por estado e bloqueio de CNPJ inativo.', 'cadastro-certo-cpf-cnpj' ) . ' ';
		echo '<a href="' . esc_url( 'https://www.deltatech.network/plugins/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Saiba mais', 'cadastro-certo-cpf-cnpj' ) . '</a>';
		echo '</p>';
	}
}
