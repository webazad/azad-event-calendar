<?php
/* 
Plugin Name: Azad Gutenberg
Description: A very simple gutenberg practice.
Plugin URi: gittechs.com/plugin/azad-gutenberg 
Author: Md. Abul Kalam Azad
Author URI: gittechs.com/author
Author Email: webdevazad@gmail.com
Version: 0.0.0.1
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: azad-gutenberg
Domain Path: /languages

@package: azad-gutenberg
*/
if(! defined('ABSPATH')) exit;

// Check for gutenberg
//if(function_exists('the_gutenberg_project')){
    if(! function_exists('azad_gutenberg')){
        function azad_gutenberg(){
            // FOR GUTENBERG CORE JS
            wp_register_script(
                'azad-gutenberg',
                plugins_url('/build/index.js',__FILE__),
                array('wp-element','wp-blocks','wp-components','wp-editor','wp-i18n'),
				filemtime(plugin_dir_path(__FILE__).'/build/index.js'),
                true
            );
            // GUTENBERG BLOCK EDITOR STYLE
            wp_register_style(
                'azad-gutenberg',
                plugins_url('/assets/css/blocks.editor.css',__FILE__),
                array('wp-edit-blocks'),
                filemtime(plugin_dir_path(__FILE__).'/assets/css/blocks.editor.css'),
                'all'
            );
            // FRONT END STYLE
            wp_register_style(
                'azad-style',
                plugins_url('/assets/css/blocks.style.css',__FILE__),
                array('wp-edit-blocks'),
                filemtime(plugin_dir_path(__FILE__).'/assets/css/blocks.style.css'),
                'all'
            );
            register_block_type('azad/azad-block',array(
                    'editor_script' => 'azad-gutenberg',
                    'editor_style' => 'azad-gutenberg',
                    'style' => 'azad-style'
                )             
            );
        }
        add_action('init','azad_gutenberg');
    }
//}

function azad_gutenberg_category($categories,$post){
	return array_merge(
		$categories,array(
			array(
				'slug'=>'azad-gutenberg',
				'title'=>'Azad Gutenberg',
			)
		)
	);
}        
add_filter('block_categories','azad_gutenberg_category',10,2);        
        
//function azad_gutenberg_one(){
//    wp_enqueue_script('azad-gutenberg',
//        plugins_dir_url(__FILES__).'azad-gutenberg.js',
//            array('wp-blocks','wp-i18n','wp-editor'),true);
//}
//add_action('enqueue_block_editor_assets','azad_gutenberg_one');
