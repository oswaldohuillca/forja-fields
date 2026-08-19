<?php
/**
 * Agrupado de la lista plana de campos.
 *
 * @package Forja
 */

declare( strict_types = 1 );

namespace Forja\Render;

use Forja\Fields\Accordion;
use Forja\Fields\Field;
use Forja\Fields\Tab;

defined( 'ABSPATH' ) || exit;

/**
 * Convierte la lista plana de campos en la estructura que hay que pintar.
 *
 * Las pestañas y los acordeones se declaran en línea, como un campo más, pero
 * significan «lo que viene después me pertenece». Esta clase resuelve esa
 * agrupación antes de que el renderer emita nada.
 *
 * La diferencia entre ambos es importante:
 *
 * - Una **pestaña** no anida. Sus campos siguen siendo hijos directos de
 *   `.acf-fields`, porque de eso dependen los bordes y el espaciado del CSS
 *   portado. Sólo se les marca a qué sección pertenecen.
 * - Un **acordeón** sí anida: sus campos van dentro de su propio panel.
 */
final class Layout {

	/**
	 * Analiza la lista de campos.
	 *
	 * @param array<int, Field> $fields Lista plana de campos.
	 * @return array{tabs: array<int, array{key: string, label: string, selected: bool}>, nodes: array<int, array<string, mixed>>}
	 */
	public static function parse( array $fields ): array {
		$tabs        = array();
		$nodes       = array();
		$current_tab = null;
		$accordion   = null;

		foreach ( $fields as $field ) {
			if ( $field instanceof Tab ) {
				$accordion = self::close( $accordion, $nodes );

				// Una pestaña marcada como final cierra el grupo: los campos
				// que sigan ya no pertenecen a ninguna sección.
				if ( $field->get( 'endpoint', false ) ) {
					$current_tab = null;
					continue;
				}

				$tabs[] = array(
					'key'      => $field->name(),
					'label'    => (string) $field->get( 'label', '' ),
					'selected' => (bool) $field->get( 'selected', false ),
				);

				$current_tab = $field->name();
				continue;
			}

			if ( $field instanceof Accordion ) {
				$accordion = self::close( $accordion, $nodes );

				if ( $field->get( 'endpoint', false ) ) {
					continue;
				}

				$accordion = array(
					'type'     => 'accordion',
					'field'    => $field,
					'tab'      => $current_tab,
					'children' => array(),
				);

				continue;
			}

			// Dentro de un acordeón abierto, todo campo normal es hijo suyo.
			if ( null !== $accordion ) {
				$accordion['children'][] = $field;
				continue;
			}

			$nodes[] = array(
				'type'  => 'field',
				'field' => $field,
				'tab'   => $current_tab,
			);
		}

		self::close( $accordion, $nodes );

		return array(
			'tabs'  => $tabs,
			'nodes' => $nodes,
		);
	}

	/**
	 * Vuelca el acordeón en curso a la lista de nodos.
	 *
	 * @param array<string, mixed>|null      $accordion Acordeón abierto, si lo hay.
	 * @param array<int, array<string, mixed>> $nodes     Lista de nodos, por referencia.
	 * @return null Siempre null, para reasignar la variable en el llamador.
	 */
	private static function close( ?array $accordion, array &$nodes ): null {
		if ( null !== $accordion ) {
			$nodes[] = $accordion;
		}

		return null;
	}
}
