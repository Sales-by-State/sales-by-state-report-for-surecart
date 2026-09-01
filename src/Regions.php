<?php
/**
 * Country and state labels for the report.
 *
 * @package SalesByStateReportForSureCart
 */

namespace SBSSC;

defined( 'ABSPATH' ) || exit;

/**
 * US, Canada, and UK subdivisions.
 *
 * SureCart does not ship a WooCommerce-style locale helper, so the codes
 * the report fills in as zero-sales rows are listed here.
 */
class Regions {

	/**
	 * Countries the report always offers.
	 *
	 * @return array<string,string>
	 */
	public static function countries() {
		return array(
			'US' => __( 'United States', 'sales-by-state-report-for-surecart' ),
			'CA' => __( 'Canada', 'sales-by-state-report-for-surecart' ),
			'GB' => __( 'United Kingdom', 'sales-by-state-report-for-surecart' ),
		);
	}

	/**
	 * State code => name for a country.
	 *
	 * @param string $country Country code.
	 * @return array<string,string>
	 */
	public static function states_for( $country ) {
		$country = strtoupper( (string) $country );

		if ( 'CA' === $country ) {
			return self::canada();
		}

		if ( 'GB' === $country ) {
			return self::united_kingdom();
		}

		if ( 'US' === $country ) {
			return self::united_states();
		}

		return array();
	}

	/**
	 * US states and DC.
	 *
	 * @return array<string,string>
	 */
	private static function united_states() {
		return array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);
	}

	/**
	 * Canadian provinces and territories.
	 *
	 * @return array<string,string>
	 */
	private static function canada() {
		return array(
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NB' => 'New Brunswick',
			'NL' => 'Newfoundland and Labrador',
			'NT' => 'Northwest Territories',
			'NS' => 'Nova Scotia',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon',
		);
	}

	/**
	 * UK countries.
	 *
	 * @return array<string,string>
	 */
	private static function united_kingdom() {
		return array(
			'ENG' => 'England',
			'SCT' => 'Scotland',
			'WLS' => 'Wales',
			'NIR' => 'Northern Ireland',
		);
	}
}
