<?php
/**
 * Registers the five public forms as shortcodes:
 *   [agrixpert_farmer_form] [agrixpert_buyer_form] [agrixpert_supplier_form]
 *   [agrixpert_cooperative_form] [agrixpert_contact_form]
 *
 * Field lists, labels, required-ness and validation messages match the
 * live Next.js forms field-for-field (checked against FarmerRegistration-
 * Form.tsx, BuyerRegistrationForm.tsx, SupplierRegistrationForm.tsx,
 * CooperativeRegistrationForm.tsx and ContactForm.tsx in the AgriXpert
 * repo). Commodity options come from the `agrixpert_value_chains` filter
 * (Phase 7 will wire this to the site_content/page_content tables so
 * admins can edit the value-chain list without touching code, exactly
 * as the current admin dashboard allows).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {

	AgriXpert_Form_Engine::register_form( 'farmer', array(
		'fields' => array(
			array( 'name' => 'fullName', 'label' => __( 'Full Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel', 'required' => true ),
			array( 'name' => 'email', 'label' => __( 'Email', 'agrixpert' ), 'type' => 'email' ),
			array( 'name' => 'location', 'label' => __( 'Farm Location', 'agrixpert' ), 'type' => 'text' ),
			array( 'name' => 'farmSize', 'label' => __( 'Farm Size (hectares)', 'agrixpert' ), 'type' => 'number', 'min' => '0', 'step' => '0.1' ),
			array( 'name' => 'cooperative', 'label' => __( 'Cooperative / Farmer Group', 'agrixpert' ), 'type' => 'text' ),
			array( 'name' => 'commodities', 'label' => __( 'Commodities You Grow', 'agrixpert' ), 'type' => 'checkbox_group', 'options' => '__value_chains__' ),
		),
		'required_message' => __( 'Please provide at least your full name and phone number.', 'agrixpert' ),
		'submit_label'      => __( 'Register as a Farmer', 'agrixpert' ),
		'success_title'     => __( 'Registration received', 'agrixpert' ),
		'success_body'      => __( 'Thank you for registering with AgriXpert. Our team will review your details and reach out about next steps, including verification and onboarding.', 'agrixpert' ),
		'insert_callback'   => array( 'AgriXpert_Registrations', 'insert_farmer' ),
	) );

	AgriXpert_Form_Engine::register_form( 'buyer', array(
		'fields' => array(
			array( 'name' => 'companyName', 'label' => __( 'Company Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'contactName', 'label' => __( 'Contact Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'email', 'label' => __( 'Email', 'agrixpert' ), 'type' => 'email', 'required' => true ),
			array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel' ),
			array( 'name' => 'commoditiesNeeded', 'label' => __( 'Commodities Needed', 'agrixpert' ), 'type' => 'checkbox_group', 'options' => '__value_chains__' ),
			array( 'name' => 'quantityRequired', 'label' => __( 'Quantity Required', 'agrixpert' ), 'type' => 'text' ),
			array( 'name' => 'qualityRequirements', 'label' => __( 'Quality Requirements', 'agrixpert' ), 'type' => 'textarea' ),
		),
		'required_message' => __( 'Please provide your company name, contact name and email.', 'agrixpert' ),
		'submit_label'      => __( 'Register as a Buyer', 'agrixpert' ),
		'success_title'     => __( 'Registration received', 'agrixpert' ),
		'success_body'      => __( 'Thank you for registering with AgriXpert. Our team will review your details and reach out about sourcing next steps.', 'agrixpert' ),
		'insert_callback'   => array( 'AgriXpert_Registrations', 'insert_buyer' ),
	) );

	AgriXpert_Form_Engine::register_form( 'supplier', array(
		'fields' => array(
			array( 'name' => 'companyName', 'label' => __( 'Company Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'contactName', 'label' => __( 'Contact Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'email', 'label' => __( 'Email', 'agrixpert' ), 'type' => 'email', 'required' => true ),
			array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel' ),
			array(
				'name'    => 'services',
				'label'   => __( 'Services Offered', 'agrixpert' ),
				'type'    => 'checkbox_group',
				'options' => array(
					'Mechanisation & Farm Equipment',
					'Input Supply (seeds, fertiliser, agrochemicals)',
					'Logistics & Transportation',
					'Storage & Warehousing',
					'Processing Equipment & Technology',
					'Financial Services',
					'Other',
				),
			),
		),
		'required_message' => __( 'Please provide your company name, contact name and email.', 'agrixpert' ),
		'submit_label'      => __( 'Register as a Supplier', 'agrixpert' ),
		'success_title'     => __( 'Registration received', 'agrixpert' ),
		'success_body'      => __( 'Thank you for registering with AgriXpert. Our team will review your details and reach out about next steps.', 'agrixpert' ),
		'insert_callback'   => array( 'AgriXpert_Registrations', 'insert_supplier' ),
	) );

	AgriXpert_Form_Engine::register_form( 'cooperative', array(
		'fields' => array(
			array( 'name' => 'name', 'label' => __( 'Cooperative Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'leadContactName', 'label' => __( 'Lead Contact Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'email', 'label' => __( 'Email', 'agrixpert' ), 'type' => 'email', 'required' => true ),
			array( 'name' => 'phone', 'label' => __( 'Phone Number', 'agrixpert' ), 'type' => 'tel' ),
			array( 'name' => 'locationLabel', 'label' => __( 'Location', 'agrixpert' ), 'type' => 'text' ),
			array( 'name' => 'memberCountEstimate', 'label' => __( 'Estimated Member Count', 'agrixpert' ), 'type' => 'number', 'min' => '0', 'step' => '1' ),
			array(
				'name'    => 'primaryCommodities',
				'label'   => __( 'Primary Commodities', 'agrixpert' ),
				'type'    => 'checkbox_group',
				'required' => true,
				'options' => array( 'Maize', 'Soybeans', 'Rice', 'Groundnuts', 'Vegetables', 'Other' ),
			),
		),
		'required_message' => __( "Please provide your cooperative's name, lead contact name and email, and select at least one commodity.", 'agrixpert' ),
		'submit_label'      => __( 'Register Cooperative', 'agrixpert' ),
		'success_title'     => __( 'Registration received', 'agrixpert' ),
		'success_body'      => __( "Thank you for registering your cooperative with AgriXpert. Our team will review your details and reach out about next steps.", 'agrixpert' ),
		'insert_callback'   => array( 'AgriXpert_Registrations', 'insert_cooperative' ),
	) );

	AgriXpert_Form_Engine::register_form( 'contact', array(
		'fields' => array(
			array( 'name' => 'name', 'label' => __( 'Name', 'agrixpert' ), 'type' => 'text', 'required' => true ),
			array( 'name' => 'email', 'label' => __( 'Email', 'agrixpert' ), 'type' => 'email', 'required' => true ),
			array(
				'name'    => 'enquiryType',
				'label'   => __( 'Enquiry Type', 'agrixpert' ),
				'type'    => 'select',
				'options' => array(
					'General Enquiry',
					'Farmer Registration & Partnership',
					'Buyer / Commodity Sourcing',
					'Partnership & Investment',
					'Media & Press',
					'Other',
				),
			),
			array( 'name' => 'message', 'label' => __( 'Message', 'agrixpert' ), 'type' => 'textarea', 'required' => true ),
		),
		'required_message' => __( 'Please provide your name, email and a message.', 'agrixpert' ),
		'submit_label'      => __( 'Send Message', 'agrixpert' ),
		'success_title'     => __( 'Message received', 'agrixpert' ),
		'success_body'      => __( "Thanks for reaching out. We'll get back to you shortly.", 'agrixpert' ),
		'insert_callback'   => array( 'AgriXpert_Registrations', 'insert_enquiry' ),
	) );
} );
