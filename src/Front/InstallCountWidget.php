<?php
/**
 * Elementor widget "Freemius Install Count".
 *
 * @package Wycan\FreemiusStats\Front
 */

declare( strict_types=1 );

namespace Wycan\FreemiusStats\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Wycan\FreemiusStats\Plugin;

/**
 * Name, category, control keys and defaults are a public contract (stored in the pages).
 * The rendered HTML and its English strings are frozen, see Shortcode.
 */
final class InstallCountWidget extends Widget_Base {

	public function get_name(): string {
		return 'freemius_install_count';
	}

	public function get_title(): string {
		return esc_html__( 'Freemius Install Count', 'wycan-freemius-stats' );
	}

	public function get_icon(): string {
		return 'eicon-counter';
	}

	public function get_categories(): array {
		return [ 'general' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Settings', 'wycan-freemius-stats' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$product_options = [];
		foreach ( Plugin::instance()->products->all() as $product ) {
			$product_options[ $product['slug'] ] = $product['name'];
		}
		if ( empty( $product_options ) ) {
			$product_options[''] = esc_html__( 'No product configured', 'wycan-freemius-stats' );
		}

		$this->add_control(
			'source_type',
			[
				'label'   => esc_html__( 'Product source', 'wycan-freemius-stats' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'fixed' => esc_html__( 'Fixed product', 'wycan-freemius-stats' ),
					'acf'   => esc_html__( 'ACF field (dynamic)', 'wycan-freemius-stats' ),
				],
				'default' => 'acf',
			]
		);

		$this->add_control(
			'product_slug',
			[
				'label'     => esc_html__( 'Product', 'wycan-freemius-stats' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $product_options,
				// The first configured product: the option order is a contract.
				'default'   => array_key_first( $product_options ),
				'condition' => [
					'source_type' => 'fixed',
				],
			]
		);

		$this->add_control(
			'acf_field_name',
			[
				'label'       => esc_html__( 'ACF field name', 'wycan-freemius-stats' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'freemius_product_slug',
				'placeholder' => 'freemius_product_slug',
				'description' => esc_html__( 'The ACF field name containing the product slug', 'wycan-freemius-stats' ),
				'condition'   => [
					'source_type' => 'acf',
				],
			]
		);

		$this->add_control(
			'display_format',
			[
				'label'   => esc_html__( 'Display format', 'wycan-freemius-stats' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'number' => esc_html__( 'Number only', 'wycan-freemius-stats' ),
					'text'   => esc_html__( 'Full text', 'wycan-freemius-stats' ),
				],
				'default' => 'number',
			]
		);

		$this->add_control(
			'active_only',
			[
				'label'        => esc_html__( 'Active installations only', 'wycan-freemius-stats' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wycan-freemius-stats' ),
				'label_off'    => esc_html__( 'No', 'wycan-freemius-stats' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'prefix_text',
			[
				'label'       => esc_html__( 'Text before', 'wycan-freemius-stats' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_attr__( 'More than', 'wycan-freemius-stats' ),
			]
		);

		$this->add_control(
			'suffix_text',
			[
				'label'       => esc_html__( 'Text after', 'wycan-freemius-stats' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_attr__( 'users', 'wycan-freemius-stats' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section',
			[
				'label' => esc_html__( 'Style', 'wycan-freemius-stats' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'text_color',
			[
				'label'     => esc_html__( 'Text color', 'wycan-freemius-stats' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .freemius-install-count-wrapper' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .freemius-install-count-wrapper',
			]
		);

		$this->add_responsive_control(
			'text_align',
			[
				'label'     => esc_html__( 'Alignment', 'wycan-freemius-stats' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'   => [
						'title' => esc_html__( 'Left', 'wycan-freemius-stats' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'wycan-freemius-stats' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => esc_html__( 'Right', 'wycan-freemius-stats' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => 'left',
				'selectors' => [
					'{{WRAPPER}} .freemius-install-count-wrapper' => 'text-align: {{VALUE}}',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$source_type = $settings['source_type'] ?? 'acf';
		$format      = $settings['display_format'] ?? 'number';
		$active_only = 'yes' === ( $settings['active_only'] ?? '' );
		$prefix      = $settings['prefix_text'] ?? '';
		$suffix      = $settings['suffix_text'] ?? '';

		if ( 'acf' === $source_type ) {
			$acf_field_name = $settings['acf_field_name'] ?? '';

			if ( empty( $acf_field_name ) ) {
				echo '<div class="freemius-install-count-wrapper">' . esc_html( 'Error: ACF field name is missing' ) . '</div>';
				return;
			}

			if ( ! AcfSlug::available() ) {
				echo '<div class="freemius-install-count-wrapper">' . esc_html( 'Error: ACF is not installed' ) . '</div>';
				return;
			}

			$product_slug = AcfSlug::read( (string) $acf_field_name );

			if ( false === $product_slug ) {
				echo '<div class="freemius-install-count-wrapper">' . sprintf(
					esc_html( 'Error: ACF field "%s" is empty' ),
					esc_html( (string) $acf_field_name )
				) . '</div>';
				return;
			}
		} else {
			$product_slug = $settings['product_slug'] ?? '';

			if ( empty( $product_slug ) ) {
				echo '<div class="freemius-install-count-wrapper">' . esc_html( 'Please configure a product in the plugin settings.' ) . '</div>';
				return;
			}
		}

		$count = Plugin::instance()->get_install_count( $product_slug, $active_only );

		if ( false === $count ) {
			echo '<div class="freemius-install-count-wrapper">' . esc_html( 'Error: Unable to retrieve data' ) . '</div>';
			return;
		}

		echo '<div class="freemius-install-count-wrapper">';

		if ( ! empty( $prefix ) ) {
			echo '<span class="prefix">' . esc_html( (string) $prefix ) . ' </span>';
		}

		echo '<span class="freemius-install-count">' . Shortcode::format( $count, 'text' === $format ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number_format_i18n output and fixed text.

		if ( ! empty( $suffix ) ) {
			echo '<span class="suffix"> ' . esc_html( (string) $suffix ) . '</span>';
		}

		echo '</div>';
	}
}
