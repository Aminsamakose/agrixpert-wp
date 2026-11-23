<?php
/**
 * Insert functions for the five registration/enquiry tables. Each mirrors
 * the exact shape of the corresponding Supabase `.insert()` call in the
 * live Next.js forms, so verification/CRM/marketplace built in later
 * phases can rely on the same column semantics.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Registrations {

	private static function uuid() {
		return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : self::fallback_uuid();
	}

	private static function fallback_uuid() {
		$data    = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	public static function insert_farmer( $v ) {
		global $wpdb;
		$id = self::uuid();
		$ok = $wpdb->insert(
			AgriXpert_Schema::table( 'farmers' ),
			array(
				'id'                   => $id,
				'full_name'            => $v['fullName'],
				'phone'                => $v['phone'],
				'email'                => $v['email'] ?: null,
				'location'             => $v['location'] ?: null,
				'farm_size_hectares'   => $v['farmSize'],
				'commodities'          => wp_json_encode( $v['commodities'] ),
				'cooperative_or_group' => $v['cooperative'] ?: null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}

	public static function insert_buyer( $v ) {
		global $wpdb;
		$id = self::uuid();
		$ok = $wpdb->insert(
			AgriXpert_Schema::table( 'buyers' ),
			array(
				'id'                   => $id,
				'company_name'         => $v['companyName'],
				'contact_name'         => $v['contactName'],
				'email'                => $v['email'],
				'phone'                => $v['phone'] ?: null,
				'commodities_needed'   => wp_json_encode( $v['commoditiesNeeded'] ),
				'quantity_required'    => $v['quantityRequired'] ?: null,
				'quality_requirements' => $v['qualityRequirements'] ?: null,
			)
		);
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}

	public static function insert_supplier( $v ) {
		global $wpdb;
		$id = self::uuid();
		$ok = $wpdb->insert(
			AgriXpert_Schema::table( 'suppliers' ),
			array(
				'id'           => $id,
				'company_name' => $v['companyName'],
				'contact_name' => $v['contactName'],
				'email'        => $v['email'],
				'phone'        => $v['phone'] ?: null,
				'services'     => wp_json_encode( $v['services'] ),
			)
		);
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}

	public static function insert_cooperative( $v ) {
		global $wpdb;
		$id = self::uuid();
		$ok = $wpdb->insert(
			AgriXpert_Schema::table( 'cooperatives' ),
			array(
				'id'                     => $id,
				'name'                   => $v['name'],
				'lead_contact_name'      => $v['leadContactName'],
				'email'                  => $v['email'],
				'phone'                  => $v['phone'] ?: null,
				'location_label'         => $v['locationLabel'] ?: null,
				'member_count_estimate'  => $v['memberCountEstimate'],
				'primary_commodities'    => wp_json_encode( $v['primaryCommodities'] ),
			)
		);
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}

	/**
	 * Self-service editable fields per type, for the Portal (see
	 * includes/public/class-portal-shortcode.php). Deliberately a smaller
	 * set than the registration form itself: identity-defining fields
	 * (name/company name) and anything already used as a lookup key
	 * (email, since AgriXpert_Actors links by email) are left out, so an
	 * actor can't silently detach themselves from their own linked
	 * record or collide with another one by editing it here.
	 */
	public static function editable_fields( $type ) {
		switch ( $type ) {
			case 'farmer':
				return array(
					array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel', 'required' => true ),
					array( 'name' => 'location', 'label' => __( 'Location', 'agrixpert' ), 'type' => 'text' ),
					array( 'name' => 'farm_size_hectares', 'label' => __( 'Farm Size (hectares)', 'agrixpert' ), 'type' => 'number', 'step' => '0.01', 'min' => '0' ),
					array( 'name' => 'cooperative_or_group', 'label' => __( 'Cooperative / Group', 'agrixpert' ), 'type' => 'text' ),
				);
			case 'buyer':
				return array(
					array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel' ),
					array( 'name' => 'quantity_required', 'label' => __( 'Quantity Required', 'agrixpert' ), 'type' => 'text' ),
					array( 'name' => 'quality_requirements', 'label' => __( 'Quality Requirements', 'agrixpert' ), 'type' => 'textarea' ),
				);
			case 'supplier':
				return array(
					array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel' ),
				);
			case 'cooperative':
				return array(
					array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel' ),
					array( 'name' => 'location_label', 'label' => __( 'Location', 'agrixpert' ), 'type' => 'text' ),
					array( 'name' => 'member_count_estimate', 'label' => __( 'Estimated Member Count', 'agrixpert' ), 'type' => 'number', 'step' => '1', 'min' => '0' ),
				);
			default:
				return array();
		}
	}

	/**
	 * Updates a registration record on behalf of its own linked WP user.
	 * The $wp_user_id -> $type/$id ownership check happens here, not just
	 * in the calling shortcode handler, so this function is safe to call
	 * directly and can never be used to edit someone else's record even
	 * if a caller forgets to check first.
	 */
	public static function update_own_record( $type, $id, $wp_user_id, array $posted ) {
		if ( ! AgriXpert_Actors::user_owns_actor( $wp_user_id, $type, $id ) ) {
			return new WP_Error( 'agx_forbidden', __( 'You do not have permission to edit this record.', 'agrixpert' ) );
		}
		global $wpdb;
		$table  = AgriXpert_Verification::table_for( $type );
		$values = array();
		foreach ( self::editable_fields( $type ) as $field ) {
			$raw = isset( $posted[ $field['name'] ] ) ? wp_unslash( $posted[ $field['name'] ] ) : '';
			switch ( $field['type'] ) {
				case 'number':
					$trimmed = trim( (string) $raw );
					$values[ $field['name'] ] = $trimmed === '' ? null : (float) $trimmed;
					break;
				case 'textarea':
					$values[ $field['name'] ] = sanitize_textarea_field( trim( (string) $raw ) );
					break;
				default:
					$values[ $field['name'] ] = sanitize_text_field( trim( (string) $raw ) );
			}
			if ( ! empty( $field['required'] ) && $values[ $field['name'] ] === '' ) {
				return new WP_Error( 'agx_required', sprintf( __( '%s is required.', 'agrixpert' ), $field['label'] ) );
			}
		}
		if ( empty( $values ) ) {
			return true;
		}
		$ok = $wpdb->update( $table, $values, array( 'id' => $id ) );
		return $ok === false ? new WP_Error( 'agx_update_failed', $wpdb->last_error ) : true;
	}

	public static function insert_enquiry( $v ) {
		global $wpdb;
		$id = self::uuid();
		$ok = $wpdb->insert(
			AgriXpert_Schema::table( 'enquiries' ),
			array(
				'id'           => $id,
				'name'         => $v['name'],
				'email'        => $v['email'],
				'enquiry_type' => $v['enquiryType'] ?: 'General Enquiry',
				'message'      => $v['message'],
			)
		);
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}
}
