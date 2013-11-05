<?php

if ( ! class_exists( 'npuGalleryUpload' ) ) {

	// Public Variables
	class npuGalleryUpload {

		public $arrImageIds          = array();
		public $arrUploadedThumbUrls = array();
		public $arrUploadedImageUrls = array();
		public $arrErrorMsg          = array();
		public $arrImageMsg          = array();
		public $arrErrorMsg_widg     = array();
		public $arrImageMsg_widg     = array();
		public $arrImageNames        = array();
		public $arrImageMeta         = array();
		public $arrShortcodeArgs     = array();
		public $strTitle             = '';
		public $strDescription       = '';
		public $strKeywords          = '';
		public $strTimeStamp         = '';
		public $strGalleryPath       = '';
		public $blnRedirectPage      = false;

		// Function: Constructors
		public function __construct() {
			add_shortcode( 'ngg_uploader', array( $this, 'shortcode_show_uploader' ) ); // Shortcode Uploader
			add_action( 'widgets_init'   , array( $this, 'npu_upload_register' ) ); // Widget Uploader
		}

		// Function: Register Widget
		public function npu_upload_register() {
			$options = get_option( 'npu_gal_upload' );
			if ( !$options ) {
				$options = array();
			}
			$widget_ops = array( 'classname' => 'npu_gallery_upload', 'description' => __( 'Upload images to a NextGEN Gallery', 'nggallery' ) );
			$control_ops = array( 'width' => 250, 'height' => 200, 'id_base' => 'npu-gallery-upload' );
			$name = __( 'NextGEN Uploader', 'nggallery' );
			$id = false;
			foreach (array_keys($options) as $o) {
				if (!isset($options[$o]['title'])) {
					continue;
				}
				$id = "npu-gallery-upload-$o";
				wp_register_sidebar_widget( $id, $name, array( $this, 'npu_upload_output' ), $widget_ops, array( 'number' => $o ) );
				wp_register_widget_control( $id, $name, array( $this, 'npu_upload_control' ), $control_ops, array( 'number' => $o ) );
			}
			if ( !$id ) {
				wp_register_sidebar_widget( 'npu-gallery-upload-1', $name, array( $this, 'npu_upload_output' ), $widget_ops, array( 'number' => -1 ) );
				wp_register_widget_control( 'npu-gallery-upload-1', $name, array( $this, 'npu_upload_control' ), $control_ops, array( 'number' => -1 ) );
			}
		}

		// Function: Widget Control
		public function npu_upload_control( $widget_args = 1 ) {
			global $wp_registered_widgets, $wpdb;
			static $updated = false;
			if ( is_numeric( $widget_args ) ) {
				$widget_args = array( 'number' => $widget_args );
			}
			$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
			extract( $widget_args, EXTR_SKIP );
			$options = get_option( 'npu_gal_upload' );
			if ( !is_array( $options ) ) {
				$options = array();
			}
			if ( !$updated && !empty( $_POST['sidebar'] ) ) {
				$sidebar = (string) $_POST['sidebar'];
				$sidebar_widgets = wp_get_sidebars_widgets();
				if (isset($sidebar_widgets[ $sidebar ])) {
					$this_sidebar = &$sidebar_widgets[ $sidebar ];
				} else {
					$this_sidebar = array();
				}
				foreach ( $this_sidebar as $_widget_id ) {
					if ( 'npu_gallery_upload' == $wp_registered_widgets[ $_widget_id ]['classname'] && isset( $wp_registered_widgets[ $_widget_id ]['params'][0]['number'] ) ) {
						$widget_number = $wp_registered_widgets[ $_widget_id ]['params'][0]['number'];
						if ( !in_array( "npu-gallery-upload-{$widget_number}", $_POST['widget-id'] ) ) {
							unset( $options[$widget_number] );
						}
					}
				}
				foreach ( (array)$_POST['widget_npu_upload'] as $widget_number => $widget_npu_upload ) {
					if ( !isset( $widget_npu_upload['gal_id'] ) && isset( $options[ $widget_number ] ) ) {
						continue;
					}
					$widget_npu_upload = stripslashes_deep( $widget_npu_upload );
					$options[ $widget_number ]['title'] = $widget_npu_upload['title'];
					$options[ $widget_number ]['gal_id'] = $widget_npu_upload['gal_id'];
				}
				update_option( 'npu_gal_upload', $options );
				$updated = true;
			}
			if ( -1 == $number ) {
				$title = 'Upload';
				$gal_id = 0;
				$number = '%i%';
			} else {
				extract( (array)$options[ $number ] );
			}
			include_once ( NGGALLERY_ABSPATH . "lib/ngg-db.php" );
			$nggdb = new nggdb();
			$gallerylist = $nggdb->find_all_galleries('gid', 'DESC');
			?>
			<p>
				<label for="npu_upload-title-<?php echo $number; ?>"><?php _e( 'Title:', 'nggallery' ); ?>
					<input id="npu_upload-title-<?php echo $number; ?>" name="widget_npu_upload[<?php echo $number; ?>][title]" type="text" class="widefat" value="<?php echo $title; ?>" />
				</label>
			</p>
			<p>
				<label for="npu_upload-id-<?php echo $number; ?>"><?php _e('Upload to :','nggallery'); ?>
					<select id="npu_upload-id-<?php echo $number; ?>" name="widget_npu_upload[<?php echo $number; ?>][gal_id]" >
						<option value="0" ><?php _e( 'Choose gallery', 'nggallery' ) ?></option>
						<?php
							foreach( $gallerylist as $gallery ) {
								$name = ( empty($gallery->title) ) ? $gallery->name : $gallery->title;
								echo '<option ' . selected( $gallery->gid , $gal_id ) . ' value="' . $gallery->gid . '">ID: ' . $gallery->gid . ' &ndash; ' . $name . '</option>';
							}
						?>
					</select>
				</label>
			</p>
			<input type="hidden" id="npu_upload-submit-<?php echo $number; ?>" name="widget_npu_upload[<?php echo $number; ?>][submit]" value="1" />
			<?php
		}

		// Function: Widget Output
		public function npu_upload_output( $args, $widget_args = 1, $options = false ) {
			extract( $args, EXTR_SKIP );
			if ( is_numeric( $widget_args ) ) {
				$widget_args = array('number' => $widget_args);
			}
			$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
			extract( $widget_args, EXTR_SKIP );
			if(!$options) {
				$options = get_option('npu_gal_upload');
			}
			$gal_id = $options[$number]['gal_id'];
			$this->handleUpload_widget();
			echo $args['before_widget'];
			echo $args['before_title'].$options[$number]['title'].$args['after_title'];
			$this->display_uploader_widget($gal_id, false);
			echo $args['after_widget'];
		}

		// Function: Add Scripts
		public function add_scripts() {
			wp_register_script( 'ngg-ajax', NGGALLERY_URLPATH . 'admin/js/ngg.ajax.js', array( 'jquery' ), '1.0.0' );
			// Setup Array
			wp_localize_script(
				'ngg-ajax',
			    'nggAjaxSetup', array(
					'url' => admin_url('admin-ajax.php'),
					'action' => 'ngg_ajax_operation',
					'operation' => '',
					'nonce' => wp_create_nonce( 'ngg-ajax' ),
					'ids' => '',
					'permission' => __( 'You do not have the correct permission', 'nggallery' ),
					'error' => __( 'Unexpected Error', 'nggallery' ),
					'failure' => __( 'Upload Failed', 'nggallery' )
				)
			);
			wp_register_script( 'ngg-progressbar', NGGALLERY_URLPATH . 'admin/js/ngg.progressbar.js', array( 'jquery' ), '1.0.0' );
			wp_register_script( 'swfupload_f10', NGGALLERY_URLPATH . 'admin/js/swfupload.js', array( 'jquery' ), '2.2.0' );
			wp_enqueue_script( 'jquery-ui-tabs' );
			wp_enqueue_script( 'mutlifile', NGGALLERY_URLPATH . 'admin/js/jquery.MultiFile.js', array( 'jquery' ), '1.1.1' );
			wp_enqueue_script( 'ngg-swfupload-handler', NGGALLERY_URLPATH . 'admin/js/swfupload.handler.js', array( 'swfupload_f10' ), '1.0.0' );
			wp_enqueue_script( 'ngg-ajax' );
			wp_enqueue_script( 'ngg-progressbar' );
		}

		/**
		 * Abstracts the image upload field.
		 * Used in the uploader, uploader widget, and Gravity Forms custom input field.
		 *
		 * @param  integer $gal_id     Gallery ID for NextGen Gallery.
		 * @param  string  $context    Context.
		 *
		 * @return string  $strOutput  HTML output for image upload input.
		 */
		public function display_image_upload_input( $gal_id = 0, $context = 'shortcode', $disable = false, $name = 'galleryselect' ) {

			$strOutput = wp_nonce_field( 'ngg_addgallery', '_wpnonce', true , false );
			$strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_pre_input', '', $this, $context );

			$disabled   = $disable ? " disabled='disabled'" : '';

			$strOutput .= "\n\t<div class=\"uploader\">";
			$strOutput .= "\n\t<input type=\"file\" name=\"imagefiles\" id=\"imagefiles\"{$disabled} />";
			$strOutput .= "\n</div>";
			$strOutput .= "\n<input type=\"hidden\" name=\"{$name}\" value=\"{$gal_id}\">";

			return $strOutput;
		}

		// Function: Shortcode Form
		public function display_uploader( $gal_id, $strDetailsPage = false, $blnShowAltText = true, $echo = true ) {
			$strOutput = '';
			if ( count( $this->arrErrorMsg ) > 0 ) {
				$strOutput .= '<div class="upload_error">';
				foreach ( $this->arrErrorMsg as $msg ) {
					$strOutput .= $msg;
				}
				$strOutput .= '</div>';
			}
			if ( count( $this->arrImageMsg ) > 0 ) {
				$strOutput .= '<div class="upload_error">';
				foreach ( $this->arrImageMsg as $msg ) {
					$strOutput .= $msg;
				}
				$strOutput .= '</div>';
			}
			if ( !is_user_logged_in() && get_option( 'npu_user_role_select' ) != 99 ) {
				$strOutput .= '<div class="need_login">';
				if( !empty( get_option( 'npu_notlogged' ) ) ) {
					$strOutput .= get_option( 'npu_notlogged' );
				} else {
					$strOutput .= __( 'You must be registered and logged in to upload images.', 'nggallery' );
				}
				$strOutput .= '</div>';
			} else {
				$npu_selected_user_role = get_option( 'npu_user_role_select' );
				if ( current_user_can( 'level_' . $npu_selected_user_role ) || get_option( 'npu_user_role_select' ) == 99 ) {

					$strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_before_form', '', $this, 'shortcode' );

					$strOutput .= '<div id="uploadimage">';
					$strOutput .= "\n\t<form name=\"uploadimage\" id=\"uploadimage_form\" method=\"POST\" enctype=\"multipart/form-data\" accept-charset=\"utf-8\" >";

					$strOutput .= $this->display_image_upload_input( $gal_id );

					if ( !$strDetailsPage ) {
						$strOutput .= "\n\t<div class=\"image_details_textfield\">";
						if ( $blnShowAltText ) {}
						$strOutput .= "\n\t</div>";
					}

					$strOutput .= $this->maybe_display_image_description();

					$strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_before_submit', '', $this, 'shortcode' );

			   	 	$strOutput .= "\n\t<div class=\"submit\"><br />";
					if ( get_option( 'npu_upload_button' ) ) {
						$strOutput .= "\n\t\t<input class=\"button-primary\" type=\"submit\" name=\"uploadimage\" id=\"uploadimage_btn\" ";
						$strOutput .= 'value="' . get_option( 'npu_upload_button' ) . '">';
					} else {
						$strOutput .= "\n\t\t<input class=\"button-primary\" type=\"submit\" name=\"uploadimage\" id=\"uploadimage_btn\" value=\"Upload\" />";
					}
					$strOutput .= "\n\t\t</div>";
					$strOutput .= "\n</form>";
					$strOutput .= "\n</div>";

					$strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_after_form', '', $this, 'shortcode' );
				}
			}

			$strOutput = apply_filters( 'npu_gallery_upload_display_uploader', $strOutput, $gal_id, $strDetailsPage, $blnShowAltText, $echo, 'shortcode', $this );

			if ( $echo ) {
	   			echo $strOutput;
			} else {
				return $strOutput;
			}
		}

		public function maybe_display_image_description( $i = false ) {

			$strOutput = '';

			if ( 'Enabled' == get_option( 'npu_image_description_select' ) ) {
				$strOutput .= '<br />' . get_option( 'npu_description_text',  __( 'Description:', 'ngg-public-uploader' ) ) . '<br />';

				$name = is_numeric( $i ) ? 'imagedescription_' . $i : 'imagedescription';

				$strOutput .= "\n\t<input type=\"text\" name=\"" . esc_attr( $name ) . "\" id=\"" . esc_attr( $name ) . "\"/>";
			}

			return $strOutput;
		}

		// Function: Widget Form
		public function display_uploader_widget( $gal_id, $strDetailsPage = false, $blnShowAltText = true, $echo = true ) {
			$strOutput = "";
			if ( count( $this->arrErrorMsg_widg ) > 0 ) {
				$strOutput .= '<div class="upload_error">';
				foreach ( $this->arrErrorMsg_widg as $msg)  {
					$strOutput .= $msg;
				}
				$strOutput .= '</div>';
			}
			if ( count( $this->arrImageMsg_widg ) > 0 ) {
				$strOutput .= '<div class="upload_error">';
				foreach ( $this->arrImageMsg_widg as $msg ) {
					$strOutput .= $msg;
				}
				$strOutput .= '</div>';
			}
			if ( !is_user_logged_in() && get_option( 'npu_user_role_select' ) != 99 ) {
				$strOutput .= '<div class="need_login">';
				if(get_option('npu_notlogged')) {
					$strOutput .= get_option('npu_notlogged');
				} else {
					$strOutput .= "You must be registered and logged in to upload images.";
				}
				$strOutput .= "</div>";
			} else {
				$npu_selected_user_role = get_option('npu_user_role_select');

				if (current_user_can('level_'. $npu_selected_user_role . '') || get_option('npu_user_role_select') == 99) {

					$strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_before_form', '', $this, 'widget' );

					$strOutput .= "<div id=\"uploadimage\">";
					$strOutput .= "\n\t<form name=\"uploadimage_widget\" id=\"uploadimage_form_widget\" method=\"POST\" enctype=\"multipart/form-data\" accept-charset=\"utf-8\" >";

					$strOutput .= $this->display_image_upload_input( $gal_id, 'widget' );

					if (!$strDetailsPage) {
						$strOutput .= "\n\t<div class=\"image_details_textfield\">";
						if ($blnShowAltText) {}
						$strOutput .= "\n\t</div>";
					}
					if(get_option('npu_image_description_select') == 'Enabled') {
						$strOutput .= "<br />";
						if(get_option('npu_description_text')) {
							$strOutput .= get_option('npu_description_text');
						} else {
							$strOutput .= __('Description:', 'ngg-public-uploader');
						}
						$strOutput .= "<br />";
						$strOutput .= "\n\t<input type=\"text\" name=\"imagedescription\" id=\"imagedescription\"/>";
					}

					$strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_before_submit', '', $this, 'widget' );

					$strOutput .= "\n\t<div class=\"submit\"><br />";
					if(get_option('npu_upload_button')) {
						$strOutput .= "\n\t\t<input class=\"button-primary\" type=\"submit\" name=\"uploadimage_widget\" id=\"uploadimage_btn\" ";
						$strOutput .= 'value="' . get_option("npu_upload_button") . '" >';
					} else {
						$strOutput .= "\n\t\t<input class=\"button-primary\" type=\"submit\" name=\"uploadimage_widget\" id=\"uploadimage_btn\" value=\"Upload\" />";
					}
					$strOutput .= "\n\t\t</div>";
					$strOutput .= "\n</form>";
					$strOutput .= "\n</div>";
				}
			}

			$strOutput = apply_filters( 'npu_gallery_upload_display_uploader', $strOutput, $gal_id, $strDetailsPage, $blnShowAltText, $echo, 'widget', $this );

			if ( $echo ) {
	   			echo $strOutput;
			} else {
				return $strOutput;
			}
		}

		// Function: Handle Upload for Shortcode
		public function handleUpload() {
			global $wpdb;
			require_once( dirname (__FILE__) . '/class.npu_uploader.php' );
			require_once( NGGALLERY_ABSPATH . '/lib/meta.php' );
			$ngg->options['swfupload'] = false;

			if ( isset( $_POST['uploadimage'] ) ) {
				check_admin_referer( 'ngg_addgallery' );
				if ( !isset( $_FILES['MF__F_0_0']['error'] ) || $_FILES['MF__F_0_0']['error'] == 0 ) {
					$objUploaderNggAdmin = new UploaderNggAdmin();
					$messagetext = $objUploaderNggAdmin->upload_images();
					$this->arrImageIds = $objUploaderNggAdmin->arrImageIds;
					$this->strGalleryPath = $objUploaderNggAdmin->strGalleryPath;
					$this->arrImageNames = $objUploaderNggAdmin->arrImageNames;
					if ( is_array( $objUploaderNggAdmin->arrThumbReturn ) && count( $objUploaderNggAdmin->arrThumbReturn ) > 0 ) {
						foreach ( $objUploaderNggAdmin->arrThumbReturn as $strReturnMsg ) {
							if ( $strReturnMsg != '1' ) {
								$this->arrErrorMsg[] = $strReturnMsg;
							}
						}

						if ( get_option( 'npu_upload_success' ) ) {
							$this->arrImageMsg[] = get_option( 'npu_upload_success' );
						} else {
							$this->arrImageMsg[] = __( 'Thank you! Your image has been submitted and is pending review.', 'nggallery' );
						}
						$this->sendEmail();
					}
					if ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) {
						foreach ( $this->arrImageIds as $imageId ) {
							$pic = nggdb::find_image( $imageId );
							$objEXIF = new nggMeta( $pic->imagePath );
							$this->strTitle = $objEXIF->get_META( 'title' );
							$this->strDescription = $objEXIF->get_META( 'caption' );
							$this->strKeywords = $objEXIF->get_META( 'keywords' );
							$this->strTimeStamp = $objEXIF->get_date_time();
							//What are we doing with this stuff? It's just reassigning, unless there's only ever 1 index in the array.
						}
					} else {
						if ( get_option( 'npu_no_file' ) ) {
							$this->arrErrorMsg[] = get_option( 'npu_no_file' );
						} else {
							$this->arrErrorMsg[] = __( 'You must select a file to upload', 'nggallery' );
						}
					}
					$this->update_details();
				} else {
					if ( get_option( 'npu_upload_failed' ) ) {
						$this->arrErrorMsg[] = get_option( 'npu_upload_failed' );
					} else {
						$this->arrErrorMsg[] = __( 'Upload failed!', 'nggallery' );
					}
				}
				if ( count( $this->arrErrorMsg ) > 0 && ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) ) {
					$gal_id = ( !empty( $_POST['galleryselect'] ) ) ? absint( $_POST['galleryselect'] ) : 1;
					foreach ( $this->arrImageIds as $intImageId ) {
						$filename = $wpdb->get_var( "SELECT filename FROM $wpdb->nggpictures WHERE pid = '$intImageId' "); //Prepare me
						if ( $filename ) {
							$gallerypath = $wpdb->get_var( $wpdb->prepare( "SELECT path FROM $wpdb->nggallery WHERE gid = %d", $gal_id ) );
							if ( $gallerypath ){
								@unlink( WINABSPATH . $gallerypath . '/thumbs/thumbs_' . $filename );
								@unlink( WINABSPATH . $gallerypath . '/' . $filename );
							}
							$delete_pic = $wpdb->delete( $wpdb->nggpictures, array( 'pid' => $intImageId ), array( '%d' ) );
						}
					}
				}
			}
		}

		// Function: Handle Upload for Widget
		public function handleUpload_widget() {
			global $wpdb;
			require_once( dirname (__FILE__). '/class.npu_uploader.php' );
			require_once( NGGALLERY_ABSPATH . '/lib/meta.php' );
			$ngg->options['swfupload'] = false;
			if ( isset( $_POST['uploadimage_widget'] ) ) {
				check_admin_referer( 'ngg_addgallery' );
				if ( ! isset( $_FILES['MF__F_0_0']['error'] ) || $_FILES['MF__F_0_0']['error'] == 0 ) {
					$objUploaderNggAdmin = new UploaderNggAdmin();
					$messagetext = $objUploaderNggAdmin->upload_images_widget();
					$this->arrImageIds = $objUploaderNggAdmin->arrImageIds;
					$this->strGalleryPath = $objUploaderNggAdmin->strGalleryPath;
					$this->arrImageNames = $objUploaderNggAdmin->arrImageNames;
					if ( is_array( $objUploaderNggAdmin->arrThumbReturn ) && count( $objUploaderNggAdmin->arrThumbReturn ) > 0 ) {
						foreach ( $objUploaderNggAdmin->arrThumbReturn as $strReturnMsg ) {
							if ( $strReturnMsg != '1' ) {
								$this->arrErrorMsg_widg[] = $strReturnMsg;
							}
						}
						if( get_option( 'npu_upload_success' ) ) {
							$this->arrImageMsg_widg[] = get_option( 'npu_upload_success' );
						} else {
							$this->arrImageMsg_widg[] = __( 'Thank you! Your image has been submitted and is pending review.', 'nggallery' );
						}
						$this->sendEmail();
					}
					if ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) {
						foreach( $this->arrImageIds as $imageId ) {
							$pic = nggdb::find_image( $imageId );
							$objEXIF = new nggMeta( $pic->imagePath );
							$this->strTitle = $objEXIF->get_META( 'title' );
							$this->strDescription = $objEXIF->get_META( 'caption' );
							$this->strKeywords = $objEXIF->get_META( 'keywords' );
							$this->strTimeStamp = $objEXIF->get_date_time();
						}
					} else {
						if( get_option( 'npu_no_file' ) ) {
							$this->arrErrorMsg_widg[] = get_option( 'npu_no_file' );
						} else {
							$this->arrErrorMsg_widg[] = __( 'You must select a file to upload', 'nggallery' );
						}
					}
					$this->update_details();
				} else {
					if ( get_option( 'npu_upload_failed' ) ) {
					   $this->arrErrorMsg_widg[] = get_option( 'npu_upload_failed' );
					} else {
					   $this->arrErrorMsg_widg[] = __( 'Upload failed!', 'nggallery' );
					}
				}
				if ( count( $this->arrErrorMsg_widg ) > 0 && ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) ) {
					$gal_id = ( !empty( $_POST['galleryselect'] ) ) ? absint( $_POST['galleryselect'] ) : 1;

					foreach ( $this->arrImageIds as $intImageId ) {
						$filename = $wpdb->get_var( "SELECT filename FROM $wpdb->nggpictures WHERE pid = '$intImageId' " );
						if ( $filename ) {
							$gallerypath = $wpdb->get_var( $wpdb->prepare( "SELECT path FROM $wpdb->nggallery WHERE gid = %d", $gal_id ) );
							if ( $gallerypath ){
								@unlink( WINABSPATH . $gallerypath . '/thumbs/thumbs_' . $filename );
								@unlink( WINABSPATH . $gallerypath . '/' . $filename );
							}
							$delete_pic = $wpdb->delete( $wpdb->nggpictures, array( 'pid' => $intImageId ), array( '%d' ) );
						}
					}
				}
			}
		}

		// Function: Update Details
		public function update_details() {
			global $wpdb;
			$arrUpdateFields = array();
			if ( isset( $_POST['imagedescription'] ) && !empty( $_POST['imagedescription'] ) ) {
				$this->strDescription = esc_sql( $_POST['imagedescription'] );
				$arrUpdateFields[] = "description = '$this->strDescription'";
			} else {
				return;
			}
			if ( isset( $_POST['alttext'] ) && !empty( $_POST['alttext'] ) ) {
				$this->strTitle = esc_sql( $_POST['alttext'] );
				$arrUpdateFields[] = "alttext = '$this->strTitle'";
			}
			if ( isset( $_POST['tags'] ) && !empty( $_POST['tags'] ) ) {
				$this->strKeywords = $_POST['tags']; //sanitize!
			}
			if ( count( $arrUpdateFields) > 0 ) {
				if ( ! get_option( 'npu_exclude_select' )  ) {
					$npu_exclude_id = 0;
				} else {
					$npu_exclude_id = 1;
				}
				$strUpdateFields = implode( ', ', $arrUpdateFields );
				$pictures = $this->arrImageIds;
				if ( count( $pictures ) > 0 ) {
					foreach ( (array)$pictures as $pid ) {
						$strQuery = "UPDATE $wpdb->nggpictures SET ";
						$strQuery .= $strUpdateFields . ", exclude = $npu_exclude_id WHERE pid = $pid";
						$wpdb->query( $strQuery );
						$arrTags = explode( ',', $this->strKeywords );
						wp_set_object_terms( $pid, $arrTags, 'ngg_tag' );
					}
				}
			}

			do_action( 'npu_gallery_upload_update_details', $this );
		}

		// Function: Shortcode
		public function shortcode_show_uploader( $atts ) {

			$default_args = apply_filters( 'npu_gallery_upload_shortcode_atts', array(
				'id'       => get_option( 'npu_default_gallery' ),
				'template' => ''
			), $this );

			$this->arrShortcodeArgs = version_compare( $GLOBALS['wp_version'], '3.6', '>=' ) ? shortcode_atts( $default_args, $atts, 'ngg_uploader' ) : shortcode_atts( $default_args, $atts );

			extract( $this->arrShortcodeArgs );

			$this->handleUpload();

			return $this->display_uploader( $id, false, true, false );
		}

		// Function: Send Email Notice
		public function sendEmail() {

			if ( get_option( 'npu_notification_email' ) ) {

				$to      = apply_filters( 'npu_gallery_upload_send_email_to'     , get_option( 'npu_notification_email' ), $this );
				$subject = apply_filters( 'npu_gallery_upload_send_email_subject', __( 'New Image Pending Review - NextGEN Public Uploader', 'nggallery' ), $this );
				$message = apply_filters( 'npu_gallery_upload_send_email_message', __( 'A new image has been submitted and is waiting to be reviewed.', 'nggallery' ), $this );

				wp_mail( $to, $subject, $message );
			}
		}

	}
}

// Create Uploader
$npuUpload = new npuGalleryUpload();