<?php
/**
 * Remoção do plugin: apaga só a configuração do Cadastro Certo.
 *
 * CPF, CNPJ, número, bairro e os outros dados de pedidos e clientes NÃO são apagados:
 * eles ficam nas chaves compartilhadas com outros plugins (_billing_cpf etc.) e fazem
 * parte do histórico da loja.
 *
 * @package CadastroCerto
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cadcerto_opcoes = array( 'tipo_pessoa', 'validar_cpf', 'validar_cnpj', 'ie', 'bairro_obrigatorio', 'celular', 'nascimento', 'sexo', 'somente_brasil', 'mascaras', 'importado_do_antigo' );
foreach ( $cadcerto_opcoes as $cadcerto_opcao ) {
	delete_option( 'cadcerto_' . $cadcerto_opcao );
}
