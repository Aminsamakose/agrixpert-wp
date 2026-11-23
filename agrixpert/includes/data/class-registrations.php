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
