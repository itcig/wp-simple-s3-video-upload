<?php
/**
 * Class Simple_S3_Upload_Settings
 *
 * This class contains the settings page for Simple S3 Video Upload plugin
 */

class Simple_S3_Upload_Settings {

    public function __construct() {
        add_action('admin_init', [$this, 'init']);
        add_action('admin_menu', [$this, 'menu']);
    }

    /**
     * Sets up the plugin by adding the settings link on the GF Settings page
     */
    public function init() {
        register_setting('simple_s3_upload_settings', 'simple_s3_upload_settings', [$this, 'validate']);

        add_settings_section('simple_s3_upload_options', 'Fill out all required fields', null, 'simple_s3_upload');
        add_settings_field('s3_bucket', 'S3 Bucket', [$this, 's3_bucket'], 'simple_s3_upload', 'simple_s3_upload_options');
        add_settings_field('aws_region', 'AWS Region', [$this, 'aws_region'], 'simple_s3_upload', 'simple_s3_upload_options');
        add_settings_field('aws_public_key', 'AWS S3 Public Key', [$this, 'aws_public_key'], 'simple_s3_upload', 'simple_s3_upload_options');
        add_settings_field('aws_secret_key', 'AWS S3 Secret Key', [$this, 'aws_secret_key'], 'simple_s3_upload', 'simple_s3_upload_options');
	    add_settings_field('aws_file_prefix', 'S3 File Prefix', [$this, 'aws_file_prefix'], 'simple_s3_upload', 'simple_s3_upload_options');
	    add_settings_field('cdn_url', 'CDN URL', [$this, 'cdn_url'], 'simple_s3_upload', 'simple_s3_upload_options');
    }

    /**
     * Adds a link to the to the settings menu
     */
    public function menu() {
        add_options_page('Simple S3 Video Upload', 'S3 Video Upload', 'manage_options', 'simple_s3_upload', array($this, 'settings_page'));
    }

    /**
     * Displays the S3 Bucket settings field
     */
    public function s3_bucket() {
        $settings = (array) get_option('simple_s3_upload_settings');
        if (isset($settings['s3_bucket'])) {
            $s3_bucket = $settings['s3_bucket'];
        } else {
            $s3_bucket = null;
        }
        echo '<input type="text" size="40" name="simple_s3_upload_settings[s3_bucket]" value="' . esc_attr($s3_bucket) . '" />';
    }
    
    /**
     * Displays the AWS Region settings field
     */
    public function aws_region() {
        $settings = (array) get_option('simple_s3_upload_settings');
        if (isset($settings['aws_region'])) {
            $aws_region = $settings['aws_region'];
        } else {
            $aws_region = null;
        }
        echo '<input type="text" size="40" name="simple_s3_upload_settings[aws_region]" value="' . esc_attr($aws_region) . '" placeholder="us-east-1" />';
    }
    
    /**
     * Displays the AWS Public Key settings field
     */
    public function aws_public_key() {
        $settings = (array) get_option('simple_s3_upload_settings');
        if (isset($settings['aws_public_key'])) {
            $aws_public_key = $settings['aws_public_key'];
        } else {
            $aws_public_key = null;
        }
        echo '<input type="text" size="40" name="simple_s3_upload_settings[aws_public_key]" value="' . esc_attr($aws_public_key) . '" placeholder="AWS IAM account that can only access S3" />';
    }
    
    /**
     * Displays the AWS Secret Key settings field
     */
    public function aws_secret_key() {
        $settings = (array) get_option('simple_s3_upload_settings');
        if (isset($settings['aws_secret_key'])) {
            $aws_secret_key = $settings['aws_secret_key'];
        } else {
            $aws_secret_key = null;
        }
        echo '<input type="text" size="40" name="simple_s3_upload_settings[aws_secret_key]" value="' . esc_attr($aws_secret_key) . '" />';
    }

	/**
	 * Displays the AWS file prefix settings field
	 */
	public function aws_file_prefix() {
		$settings = (array) get_option('simple_s3_upload_settings');
		if (isset($settings['aws_file_prefix'])) {
			$aws_secret_key = $settings['aws_file_prefix'];
		} else {
			$aws_secret_key = null;
		}
		echo '<input type="text" size="40" name="simple_s3_upload_settings[aws_file_prefix]" value="' . esc_attr($aws_secret_key) . '" placeholder="videos" />';
		echo '<p>The prefix (folder) that all videos will be uploaded to (optional)</p>';
	}

	/**
	 * Displays the CDN Url settings field
	 */
	public function cdn_url() {
		$settings = (array) get_option('simple_s3_upload_settings');
		if (isset($settings['cdn_url'])) {
			$aws_secret_key = $settings['cdn_url'];
		} else {
			$aws_secret_key = null;
		}
		echo '<input type="text" size="40" name="simple_s3_upload_settings[cdn_url]" value="' . esc_attr($aws_secret_key) . '" />';
		echo '<p>If using a CDN like Cloudfront, enter the full URL for this (optional)</p>';
	}

	/**
	 * Validates the user input
	 * @param array $input POST data
	 * @return array Sanitized POST data
	 */
    public function validate($input) {
        return $input;
    }

    /**
     * Output the main settings page with the title and form
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <div id="icon-options-general" class="icon32"><br></div>
            <h2>Simple S3 Video Upload</h2>
            <form method="post" action="options.php">
        <?php settings_fields('simple_s3_upload_settings'); ?>
        <?php do_settings_sections('simple_s3_upload'); ?>
        <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

}

/**
 * Init settings
 */
$simpleS3UploadSettings = new Simple_S3_Upload_Settings();
