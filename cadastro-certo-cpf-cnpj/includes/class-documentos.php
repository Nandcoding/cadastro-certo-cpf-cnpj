<?php
/**
 * Validação e formatação de CPF e CNPJ (numérico e alfanumérico).
 *
 * Esta classe não usa funções do WordPress: os testes a carregam com PHP puro (definindo ABSPATH).
 *
 * @package CadastroCerto
 */

namespace Deltatech\CadastroCerto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regras de CPF e CNPJ.
 *
 * CNPJ alfanumérico: os 12 primeiros caracteres podem ser 0-9 ou A-Z; os 2 últimos
 * (dígitos verificadores) são sempre números. O valor de cada caractere é o código
 * ASCII menos 48 (0 = 0 … 9 = 9, A = 17 … Z = 42), com pesos de 2 a 9 da direita
 * para a esquerda e módulo 11 (resto 0 ou 1 dá dígito 0). Fonte: Serpro/Receita
 * Federal, "Cálculo dos dígitos verificadores de CNPJ alfanumérico".
 */
class Documentos {

	/**
	 * Tira tudo o que não é número.
	 *
	 * @param string $valor Valor digitado.
	 * @return string
	 */
	public static function so_numeros( $valor ) {
		return preg_replace( '/[^0-9]/', '', (string) $valor );
	}

	/**
	 * Normaliza um CNPJ: maiúsculas, sem pontuação nem espaços.
	 *
	 * @param string $valor Valor digitado.
	 * @return string
	 */
	public static function normalizar_cnpj( $valor ) {
		return preg_replace( '/[^0-9A-Z]/', '', strtoupper( (string) $valor ) );
	}

	/**
	 * Confere um CPF pelo dígito verificador.
	 *
	 * @param string $valor CPF com ou sem pontuação.
	 * @return bool
	 */
	public static function cpf_valido( $valor ) {
		$valor = (string) $valor;
		// Só aceita números e pontuação comum; letras invalidam.
		if ( preg_match( '/[^0-9.\-\s]/', $valor ) ) {
			return false;
		}
		$cpf = self::so_numeros( $valor );
		if ( 11 !== strlen( $cpf ) || preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
			return false;
		}
		for ( $t = 9; $t < 11; $t++ ) {
			$soma = 0;
			for ( $i = 0; $i < $t; $i++ ) {
				$soma += (int) $cpf[ $i ] * ( ( $t + 1 ) - $i );
			}
			$digito = ( ( 10 * $soma ) % 11 ) % 10;
			if ( (int) $cpf[ $t ] !== $digito ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Calcula um dígito verificador de CNPJ sobre a base informada (12 ou 13 caracteres).
	 *
	 * @param string $base Caracteres já normalizados.
	 * @return int
	 */
	public static function cnpj_digito( $base ) {
		$soma = 0;
		$peso = 2;
		for ( $i = strlen( $base ) - 1; $i >= 0; $i-- ) {
			$soma += ( ord( $base[ $i ] ) - 48 ) * $peso;
			$peso  = ( 9 === $peso ) ? 2 : $peso + 1;
		}
		$resto = $soma % 11;
		return ( $resto < 2 ) ? 0 : 11 - $resto;
	}

	/**
	 * Confere um CNPJ numérico ou alfanumérico pelo dígito verificador.
	 *
	 * Letras minúsculas são aceitas e tratadas como maiúsculas.
	 *
	 * @param string $valor CNPJ com ou sem pontuação.
	 * @return bool
	 */
	public static function cnpj_valido( $valor ) {
		$valor = strtoupper( trim( (string) $valor ) );
		// Só caracteres de CNPJ e pontuação comum.
		if ( preg_match( '/[^0-9A-Z.\/\-\s]/', $valor ) ) {
			return false;
		}
		$cnpj = self::normalizar_cnpj( $valor );
		if ( ! preg_match( '/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj ) ) {
			return false;
		}
		// Sequências repetidas (00000000000000, 11111111111111…) passam no cálculo mas não existem.
		if ( preg_match( '/^(.)\1{13}$/', $cnpj ) ) {
			return false;
		}
		$base = substr( $cnpj, 0, 12 );
		$dv1  = self::cnpj_digito( $base );
		$dv2  = self::cnpj_digito( $base . $dv1 );
		return substr( $cnpj, 12, 2 ) === $dv1 . $dv2;
	}

	/**
	 * Diz se o CNPJ tem letras (formato alfanumérico).
	 *
	 * @param string $valor CNPJ.
	 * @return bool
	 */
	public static function cnpj_alfanumerico( $valor ) {
		return (bool) preg_match( '/[A-Z]/', self::normalizar_cnpj( $valor ) );
	}

	/**
	 * Formata um CPF como 000.000.000-00 (devolve o valor original se não tiver 11 números).
	 *
	 * @param string $valor CPF.
	 * @return string
	 */
	public static function formatar_cpf( $valor ) {
		$cpf = self::so_numeros( $valor );
		if ( 11 !== strlen( $cpf ) ) {
			return trim( (string) $valor );
		}
		return substr( $cpf, 0, 3 ) . '.' . substr( $cpf, 3, 3 ) . '.' . substr( $cpf, 6, 3 ) . '-' . substr( $cpf, 9, 2 );
	}

	/**
	 * Formata um CNPJ como AA.AAA.AAA/AAAA-00 (devolve o valor original se não tiver 14 caracteres).
	 *
	 * @param string $valor CNPJ.
	 * @return string
	 */
	public static function formatar_cnpj( $valor ) {
		$cnpj = self::normalizar_cnpj( $valor );
		if ( 14 !== strlen( $cnpj ) ) {
			return trim( (string) $valor );
		}
		return substr( $cnpj, 0, 2 ) . '.' . substr( $cnpj, 2, 3 ) . '.' . substr( $cnpj, 5, 3 ) . '/' . substr( $cnpj, 8, 4 ) . '-' . substr( $cnpj, 12, 2 );
	}

	/**
	 * Normaliza a inscrição estadual: "isento" vira ISENTO; o resto fica em maiúsculas, sem espaços nas pontas.
	 *
	 * @param string $valor Inscrição estadual.
	 * @return string
	 */
	public static function normalizar_ie( $valor ) {
		$valor = strtoupper( trim( (string) $valor ) );
		if ( preg_match( '/^ISENT[OA]$/', $valor ) ) {
			return 'ISENTO';
		}
		return preg_replace( '/[^0-9A-Z.\/\-]/', '', $valor );
	}

	/**
	 * Confere a forma da inscrição estadual (ISENTO ou de 2 a 14 números, com pontuação opcional).
	 * A regra de cada estado fica fora do plugin gratuito.
	 *
	 * @param string $valor Inscrição estadual já normalizada.
	 * @return bool
	 */
	public static function ie_aceitavel( $valor ) {
		if ( 'ISENTO' === $valor ) {
			return true;
		}
		$numeros = self::so_numeros( $valor );
		return strlen( $numeros ) >= 2 && strlen( $numeros ) <= 14 && (bool) preg_match( '/^[0-9A-Z.\/\-]+$/', $valor );
	}

	/**
	 * Converte data dd/mm/aaaa ou aaaa-mm-dd para dd/mm/aaaa (formato gravado pelo plugin antigo).
	 * Devolve string vazia se a data não existir.
	 *
	 * @param string $valor Data.
	 * @return string
	 */
	public static function data_br( $valor ) {
		$valor = trim( (string) $valor );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m ) ) {
			$a = (int) $m[1];
			$b = (int) $m[2];
			$c = (int) $m[3];
		} elseif ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m ) ) {
			$a = (int) $m[3];
			$b = (int) $m[2];
			$c = (int) $m[1];
		} else {
			return '';
		}
		if ( ! checkdate( $b, $c, $a ) ) {
			return '';
		}
		return sprintf( '%02d/%02d/%04d', $c, $b, $a );
	}

	/**
	 * Converte dd/mm/aaaa para aaaa-mm-dd (formato do campo de data do checkout em blocos).
	 *
	 * @param string $valor Data.
	 * @return string
	 */
	public static function data_iso( $valor ) {
		$br = self::data_br( $valor );
		if ( '' === $br ) {
			return '';
		}
		list( $d, $m, $a ) = explode( '/', $br );
		return $a . '-' . $m . '-' . $d;
	}
}
