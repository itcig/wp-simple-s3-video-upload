<?php
/**
  Plugin Name: Simple S3 Video Upload
  Plugin URI: http://www.capinfogroup.com/
  Description: Video upload and transcoding for Wordpress
  Version: 1.1
  Author: Brad Ash
 */

DEFINE('SIMPLE_S3_UPLOAD_PATH', plugin_dir_path(__FILE__));

require_once(SIMPLE_S3_UPLOAD_PATH . 'admin/settings.php');
require_once(SIMPLE_S3_UPLOAD_PATH . 'ajax.php');

class Simple_S3_Upload {

    private $awsS3Bucket;
    private $awsRegion;
    private $awsPublicKey;
    private $awsSecretKey;

	function __construct() {
		$this->actions();
		$this->filters();

		/**
		 * Load AWS settings from database
		 */
		$settings = (array) get_option('simple_s3_upload_settings');

		$this->awsS3Bucket = isset($settings['s3_bucket']) ? $settings['s3_bucket'] : null;
		$this->awsRegion = isset($settings['aws_region']) ? $settings['aws_region'] : null;

		// IAM s3 read/write
		$this->awsPublicKey = isset($settings['aws_public_key']) ? $settings['aws_public_key'] : null;
		$this->awsSecretKey = isset($settings['aws_secret_key']) ? $settings['aws_secret_key'] : null;

		$this->awsFilePrefix = isset($settings['aws_file_prefix']) ? $settings['aws_file_prefix'] : 'videos';
		$this->cdnUrl = isset($settings['cdn_url']) ? trailingslashit($settings['cdn_url']) : sprintf('https://s3.amazonaws.com/%s/', $this->awsS3Bucket);
	}

	private function actions() {
		add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
		add_action('media_upload_video_upload', [$this, 'uploaded_media_handle']);
		add_action('media_upload_video_upload_new', [$this, 'new_media_handle']);

	}

	private function filters() {
		add_filter('media_upload_tabs', [$this, 'media_menu']);
    }

	/**
	 * Load plugin JS with localized variables
	 */
	public function admin_scripts() {
		// Plugin uploader JS
		wp_register_script('simple-s3-upload', plugins_url('js/simple-s3-upload.js', __FILE__), FALSE, NULL);

		wp_localize_script('simple-s3-upload', 'simple_s3_upload', [
			'ajax_url'       => admin_url('admin-ajax.php'),
			'plugin_url'     => plugins_url('', __FILE__),
			's3_bucket'      => $this->awsS3Bucket,
			'aws_region'     => $this->awsRegion,
			'aws_public_key' => $this->awsPublicKey,
			'aws_secret_key' => $this->awsSecretKey,
            'cdn_url'        => $this->cdnUrl,
            'file_prefix'    => $this->awsFilePrefix
		]);

		wp_enqueue_script('simple-s3-upload');

		// Plugin uploader CSS
		wp_enqueue_style('simple-s3-upload', plugins_url('css/simple-s3-upload.css', __FILE__), FALSE, NULL);

		// Fine Uploader scripts + styles
		wp_enqueue_style('fine-uploader', plugins_url('plugins/fine-uploader/fine-uploader-new.min.css', __FILE__), FALSE, NULL);
		wp_enqueue_script('fine-uploader', plugins_url('plugins/fine-uploader/s3.jquery.fine-uploader.min.js', __FILE__), [], NULL, TRUE);

		// AWS JS SDK
		wp_enqueue_script('aws-sdk', plugins_url('plugins/aws/aws-sdk-2.336.0.min.js', __FILE__), [], NULL, TRUE);
	}

	/**
     * Add tabs to the menu of the "Add media" window
     */
	public function media_menu($tabs) {
		$newtab = [
			'video_upload'     => 'Video Uploads',
			'video_upload_new' => 'Upload New Video'
		];
		return array_merge($tabs, $newtab);
	}

	/**
	 * Make our iframe show up in the "Add media" page
	 */
	public function uploaded_media_handle() {
		return wp_iframe([$this, 'media_video_upload_page']);
	}

	/**
     * Output the contents of the video upload tab in the "Add media" page.
     * The actual videos are populated via Javascript.
     */
	public function media_video_upload_page() {
		media_upload_header();

		?>
        <form class="media-upload-form type-form validate" id="video-form"
              enctype="multipart/form-data" method="post" action="">
            <h3 class="media-title">Embed uploaded videos</h3>
            <div id="media-items">
                <div id="video_upload-video-box" class="media-item">
                    <div id='video_upload-list-wrapper'>
                        <ul id='video_upload-video-list'></ul>
                    </div>
                    <button id='video_upload-refresh-button' class='button-primary'>Refresh list</button>
                </div>
            </div>
        </form>
		<?php
	}

	public function new_media_handle() {
		return wp_iframe([$this, 'media_video_upload_new_page']);
	}

	/**
     * Output the contents of the video upload tab in the "Add media" page
     */
	public function media_video_upload_new_page() {
		media_upload_header();

		?>
        <div class="wrap">
            <h3 class="media-title">Upload new video to Amazon</h3>
            <div id="fine-uploader-s3"></div>
        </div>
        <script type="text/template" id="qq-template-s3">
            <div class="qq-uploader-selector qq-uploader qq-gallery"
                 qq-drop-area-text="Drop files here">
                <div class="qq-upload-drop-area-selector qq-upload-drop-area"
                     qq-hide-dropzone>
                    <span class="qq-upload-drop-area-text-selector"></span>
                </div>
                <div class="qq-upload-button-selector qq-upload-button">
                    <div>Upload a file</div>
                </div>
                <span class="qq-drop-processing-selector qq-drop-processing">
                <span>Processing dropped files...</span>
                <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
            </span>
                <ul class="qq-upload-list-selector qq-upload-list" role="region"
                    aria-live="polite" aria-relevant="additions removals">
                    <li>
                        <span role="status"
                              class="qq-upload-status-text-selector qq-upload-status-text"></span>
                        <div class="qq-progress-bar-container-selector qq-progress-bar-container">
                            <div role="progressbar" aria-valuenow="0"
                                 aria-valuemin="0" aria-valuemax="100"
                                 class="qq-progress-bar-selector qq-progress-bar"></div>
                        </div>
                        <span class="qq-upload-spinner-selector qq-upload-spinner"></span>
                        <div class="qq-thumbnail-wrapper">
                            <a class="preview-link" target="_blank">
                                <img class="qq-thumbnail-selector"
                                     qq-max-size="120" qq-server-scale>
                            </a>
                        </div>
                        <button type="button"
                                class="qq-upload-cancel-selector qq-upload-cancel">
                            X
                        </button>
                        <button type="button"
                                class="qq-upload-retry-selector qq-upload-retry">
                            <span class="qq-btn qq-retry-icon"
                                  aria-label="Retry"></span>
                            Retry
                        </button>

                        <div class="qq-file-info">
                            <div class="qq-file-name">
                                <span class="qq-upload-file-selector qq-upload-file"></span>
                                <span class="qq-edit-filename-icon-selector qq-edit-filename-icon"
                                      aria-label="Edit filename"></span>
                            </div>
                            <input class="qq-edit-filename-selector qq-edit-filename"
                                   tabindex="0" type="text">
                            <span class="qq-upload-size-selector qq-upload-size"></span>
                            <button type="button"
                                    class="qq-btn qq-upload-delete-selector qq-upload-delete">
                                <span class="qq-btn qq-delete-icon"
                                      aria-label="Delete"></span>
                            </button>
                            <button type="button"
                                    class="qq-btn qq-upload-pause-selector qq-upload-pause">
                                <span class="qq-btn qq-pause-icon"
                                      aria-label="Pause"></span>
                            </button>
                            <button type="button"
                                    class="qq-btn qq-upload-continue-selector qq-upload-continue">
                                <span class="qq-btn qq-continue-icon"
                                      aria-label="Continue"></span>
                            </button>
                        </div>
                    </li>
                </ul>

                <dialog class="qq-alert-dialog-selector">
                    <div class="qq-dialog-message-selector"></div>
                    <div class="qq-dialog-buttons">
                        <button type="button" class="qq-cancel-button-selector">
                            Close
                        </button>
                    </div>
                </dialog>

                <dialog class="qq-confirm-dialog-selector">
                    <div class="qq-dialog-message-selector"></div>
                    <div class="qq-dialog-buttons">
                        <button type="button" class="qq-cancel-button-selector">
                            No
                        </button>
                        <button type="button" class="qq-ok-button-selector">
                            Yes
                        </button>
                    </div>
                </dialog>

                <dialog class="qq-prompt-dialog-selector">
                    <div class="qq-dialog-message-selector"></div>
                    <input type="text">
                    <div class="qq-dialog-buttons">
                        <button type="button" class="qq-cancel-button-selector">
                            Cancel
                        </button>
                        <button type="button" class="qq-ok-button-selector">Ok
                        </button>
                    </div>
                </dialog>
            </div>
        </script>
		<?php
	}
}

/**
 * Init plugin
 */
add_action('admin_init', function() {
	$simpleS3Upload = new Simple_S3_Upload();
});
