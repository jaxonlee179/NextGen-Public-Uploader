<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Is there any way we can handle the uploading more gracefully?
Does NGG rely on not uploading to the media library, making the functions available there not applicable?
 */

// Get NextGEN Gallery Functions
require_once ( NGGALLERY_ABSPATH . '/admin/functions.php' );

class UploaderNggAdmin extends nggAdmin {

	// Public Variables
	public $arrImageIds      = array();
	public $arrImageNames    = array();
	public $arrThumbReturn   = array();
	public $arrEXIF          = array();
	public $arrErrorMsg      = array();
	public $arrErrorMsg_widg = array();
	public $strFileName      = '';
	public $strGalleryPath   = '';
	public $blnRedirectPage  = false;

	function upload_images() {
		$storage        = C_Gallery_Storage::get_instance();
		$image_mapper   = C_Image_Mapper::get_instance();
		$gallery_mapper = C_Gallery_Mapper::get_instance();

		// Get Gallery ID
		$galleryID = absint( $_POST['galleryselect'] );
		if ( 0 == $galleryID ) {
			$galleryID = get_option( 'npu_default_gallery' );
			if ( empty( $galleryID ) ) {
				self::show_error( __( 'No gallery selected.', 'nextgen-public-uploader' ) );
				return;
			}
		}

		// Get the Gallery
		$gallery = $gallery_mapper->find( $galleryID );
		if ( ! $gallery->path ) {
			self::show_error( __('Failure in database, no gallery path set.', 'nextgen-public-uploader' ) );
			return;
		}

		// Read Image List
		foreach( $_FILES as $key => $value ) {
			if ( 0 == $_FILES[ $key ]['error'] ) {
				try {
					if ( $storage->is_image_file( $_FILES[ $key ]['tmp_name'] ) ) {
						$image = $storage->object->upload_base64_image(
							$gallery,
							file_get_contents( $_FILES[ $key ]['tmp_name'] ),
							$_FILES[$key]['name']
						);

						if ( get_option( 'npu_exclude_select' ) ) {
							$image->exclude = 1;
							$image_mapper->save( $image );
						}

						// Add to Image and Dir List
						$this->arrImgNames[]  = $image->filename;
						$this->arrImageIds[]  = $image->id();
						$this->strGalleryPath = $gallery->path;
					} else {
						unlink( $_FILES[ $key ]['tmp_name'] );
						$error_msg = sprintf(
							__( '<strong>%s</strong> is not a valid file.', 'nextgen-public-uploader' ),
							$_FILES[ $key ]['name']
						);
						self::show_error( $error_msg );
						continue;
					}
				} catch (E_NggErrorException $ex) {
					self::show_error('<strong>' . $ex->getMessage() . '</strong>');
					continue;
				} catch (Exception $ex) {
					self::show_error('<strong>' . $ex->getMessage() . '</strong>');
					continue;
				}
			}
		}
	}

	public static function show_error( $msg ) {
		if ( is_user_logged_in() && apply_filters( 'uploader_ngg_admin_show_error', true ) ) {
			nggGallery::show_error( $msg );
		}
	}
}
