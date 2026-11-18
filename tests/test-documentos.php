<?php
/**
 * Testes dos validadores (PHP puro, sem WordPress).
 *
 * Uso: php tests/test-documentos.php
 *
 * @package CadastroCerto
 */

define( 'CADCERTO_TESTES', true );
define( 'ABSPATH', __DIR__ . '/' ); // A classe testada recusa carga direta sem ABSPATH.
require dirname( __DIR__ ) . '/cadastro-certo-cpf-cnpj/includes/class-documentos.php';

use Deltatech\CadastroCerto\Documentos;

$falhas = 0;
$total  = 0;

/**
 * Registra uma verificação.
 *
 * @param string $nome     Descrição.
 * @param mixed  $obtido   Resultado.
 * @param mixed  $esperado Esperado.
 */
function confere( $nome, $obtido, $esperado ) {
	global $falhas, $total;
	$total++;
	if ( $obtido === $esperado ) {
		echo "ok   - $nome\n";
		return;
	}
	$falhas++;
	echo "FALHA - $nome: obtido " . var_export( $obtido, true ) . ', esperado ' . var_export( $esperado, true ) . "\n";
}

// Exemplo oficial do Serpro/Receita: 12.ABC.345/01DE-35.
confere( 'DV1 do exemplo oficial 12ABC34501DE = 3', Documentos::cnpj_digito( '12ABC34501DE' ), 3 );
confere( 'DV2 do exemplo oficial 12ABC34501DE3 = 5', Documentos::cnpj_digito( '12ABC34501DE3' ), 5 );

$cnpj_validos = array(
	'12.ABC.345/01DE-35' => 'alfanumérico oficial, com máscara',
	'12ABC34501DE35'     => 'alfanumérico oficial, sem máscara',
	'12abc34501de35'     => 'alfanumérico oficial, minúsculas',
	' 12.abc.345/01de-35 ' => 'alfanumérico com espaços nas pontas',
	'11.222.333/0001-81' => 'numérico clássico',
	'11222333000181'     => 'numérico sem máscara',
	'11.444.777/0001-61' => 'numérico clássico 2',
	'00.000.000/0001-91' => 'numérico (Banco do Brasil)',
);
foreach ( $cnpj_validos as $cnpj => $nome ) {
	confere( "CNPJ válido: $cnpj ($nome)", Documentos::cnpj_valido( $cnpj ), true );
}

$cnpj_invalidos = array(
	'12.ABC.345/01DE-36' => 'DV2 errado',
	'12.ABC.345/01DE-45' => 'DV1 errado',
	'12.ABD.345/01DE-35' => 'letra trocada',
	'12ABC34501DE3A'     => 'letra no dígito verificador',
	'12ABC34501DE3'      => '13 caracteres',
	'12ABC34501DE355'    => '15 caracteres',
	'12.ABC.345/01DÉ-35' => 'letra acentuada',
	'12.ABC.345/01D@-35' => 'símbolo',
	'00000000000000'     => 'zeros',
	'11111111111111'     => 'repetido',
	'AAAAAAAAAAAA00'     => 'letras repetidas sem DV válido',
	'11.222.333/0001-82' => 'numérico com DV errado',
	''                   => 'vazio',
	'529.982.247-25'     => 'um CPF no lugar do CNPJ',
);
foreach ( $cnpj_invalidos as $cnpj => $nome ) {
	confere( "CNPJ inválido: '$cnpj' ($nome)", Documentos::cnpj_valido( $cnpj ), false );
}

// Todo CNPJ numérico válido no cálculo antigo continua válido no novo (ASCII-48 de dígito = dígito).
mt_srand( 42 );
$iguais = true;
for ( $n = 0; $n < 2000; $n++ ) {
	$base = '';
	for ( $i = 0; $i < 12; $i++ ) {
		$base .= (string) mt_rand( 0, 9 );
	}
	// Cálculo numérico tradicional, escrito à parte.
	$pesos1 = array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );
	$pesos2 = array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );
	$s      = 0;
	foreach ( $pesos1 as $i => $p ) {
		$s += (int) $base[ $i ] * $p;
	}
	$d1 = ( $s % 11 < 2 ) ? 0 : 11 - $s % 11;
	$b2 = $base . $d1;
	$s  = 0;
	foreach ( $pesos2 as $i => $p ) {
		$s += (int) $b2[ $i ] * $p;
	}
	$d2 = ( $s % 11 < 2 ) ? 0 : 11 - $s % 11;
	if ( ! Documentos::cnpj_valido( $base . $d1 . $d2 ) && ! preg_match( '/^(.)\1{13}$/', $base . $d1 . $d2 ) ) {
		$iguais = false;
	}
}
confere( '2000 CNPJs numéricos aleatórios com DV tradicional são aceitos', $iguais, true );

$cpf_validos = array( '529.982.247-25', '52998224725', '111.444.777-35' );
foreach ( $cpf_validos as $cpf ) {
	confere( "CPF válido: $cpf", Documentos::cpf_valido( $cpf ), true );
}
$cpf_invalidos = array(
	'529.982.247-24' => 'DV errado',
	'111.111.111-11' => 'repetido',
	'000.000.000-00' => 'zeros',
	'5299822472'     => '10 números',
	'529982247255'   => '12 números',
	'52998224A25'    => 'letra',
	''               => 'vazio',
);
foreach ( $cpf_invalidos as $cpf => $nome ) {
	confere( "CPF inválido: '$cpf' ($nome)", Documentos::cpf_valido( $cpf ), false );
}

confere( 'Formata CNPJ alfanumérico', Documentos::formatar_cnpj( '12abc34501de35' ), '12.ABC.345/01DE-35' );
confere( 'Formata CNPJ numérico', Documentos::formatar_cnpj( '11222333000181' ), '11.222.333/0001-81' );
confere( 'Formata CPF', Documentos::formatar_cpf( '52998224725' ), '529.982.247-25' );
confere( 'Detecta CNPJ alfanumérico', Documentos::cnpj_alfanumerico( '12.ABC.345/01DE-35' ), true );
confere( 'Detecta CNPJ numérico', Documentos::cnpj_alfanumerico( '11.222.333/0001-81' ), false );
confere( 'IE isento', Documentos::normalizar_ie( ' isento ' ), 'ISENTO' );
confere( 'IE aceita ISENTO', Documentos::ie_aceitavel( 'ISENTO' ), true );
confere( 'IE aceita número', Documentos::ie_aceitavel( Documentos::normalizar_ie( '110.042.490.114' ) ), true );
confere( 'IE recusa texto', Documentos::ie_aceitavel( Documentos::normalizar_ie( 'abc' ) ), false );
confere( 'Data ISO para BR', Documentos::data_br( '1990-02-28' ), '28/02/1990' );
confere( 'Data BR fica BR', Documentos::data_br( '28/02/1990' ), '28/02/1990' );
confere( 'Data inexistente', Documentos::data_br( '31/02/1990' ), '' );
confere( 'Data BR para ISO', Documentos::data_iso( '28/02/1990' ), '1990-02-28' );

echo "\n$total verificações, $falhas falha(s). PHP " . PHP_VERSION . "\n";
exit( $falhas ? 1 : 0 );
