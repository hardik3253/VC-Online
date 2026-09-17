<?php
/**
 * Plugin Name: Tutor Dummy Review Generator
 * Plugin URI: https://vcacademy.in
 * Description: Generate dummy users, enroll them into Tutor LMS courses and create course reviews.
 * Version: 1.0.0
 * Author: Hardik Prajapati
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TDRG_PATH', plugin_dir_path( __FILE__ ) );
define( 'TDRG_URL', plugin_dir_url( __FILE__ ) );

require_once TDRG_PATH . 'includes/users.php';
require_once TDRG_PATH . 'includes/enroll.php';
require_once TDRG_PATH . 'includes/reviews.php';

class TDRG_Plugin {

    public function __construct() {

        add_action( 'admin_menu', [ $this, 'menu' ] );

    }

    public function menu() {

        add_menu_page(
            'Tutor Dummy Reviews',
            'Tutor Dummy Reviews',
            'manage_options',
            'tdrg',
            [ $this, 'page' ],
            'dashicons-star-filled',
            60
        );

    }

    public function page() {
        ?>

        <div class="wrap">

            <h1>Tutor Dummy Review Generator</h1>

            <p>This plugin creates demo users, enrolls them into all Tutor LMS courses and generates reviews.</p>

            <hr>

            <form method="post">

                <?php wp_nonce_field( 'tdrg_action' ); ?>

                <p>

                    <button class="button button-primary"
                            name="tdrg_action"
                            value="users">
                        Create Demo Users
                    </button>

                </p>

                <p>

                    <button class="button button-primary"
                            name="tdrg_action"
                            value="enroll">
                        Enroll Users
                    </button>

                </p>

                <p>

                    <button class="button button-primary"
                            name="tdrg_action"
                            value="reviews">
                        Generate Reviews
                    </button>

                </p>

            </form>

        </div>

        <?php

        if ( empty( $_POST['tdrg_action'] ) ) {
            return;
        }

        check_admin_referer( 'tdrg_action' );

        switch ( sanitize_text_field( $_POST['tdrg_action'] ) ) {

            case 'users':
                tdrg_create_demo_users();
                break;

            case 'enroll':
                tdrg_enroll_users();
                break;

            case 'reviews':
                tdrg_generate_reviews();
                break;

        }

    }

}

new TDRG_Plugin();