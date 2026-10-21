<?php
/**
 * Unit tests for the callook.info response parser (no WordPress needed).
 *
 * @package AR_Net_Logger
 */

use PHPUnit\Framework\TestCase;

/**
 * ARNL_Callsign_Lookup::parse_callook().
 */
class CallsignLookupTest extends TestCase {

	/**
	 * A callook.info "VALID" response.
	 *
	 * @param array $overrides Fields to replace.
	 * @return array
	 */
	private function response( array $overrides = array() ) {
		return array_replace_recursive(
			array(
				'status'   => 'VALID',
				'type'     => 'PERSON',
				'current'  => array(
					'callsign'  => 'KD0ABC',
					'operClass' => 'GENERAL',
				),
				'previous' => array(
					'callsign'  => '',
					'operClass' => '',
				),
				'trustee'  => array(
					'callsign' => '',
					'name'     => '',
				),
				'name'     => 'JOHN Q PUBLIC',
				'address'  => array(
					'line1' => '123 MAIN ST',
					'line2' => 'COLORADO SPRINGS, CO 80903',
					'attn'  => '',
				),
				'location' => array(
					'latitude'   => '38.8',
					'longitude'  => '-104.8',
					'gridsquare' => 'DM78qt',
				),
			),
			$overrides
		);
	}

	public function test_person() {
		$station = ARNL_Callsign_Lookup::parse_callook( $this->response() );
		$this->assertSame( 'KD0ABC', $station['callsign'] );
		$this->assertSame( 'John', $station['name'] );
		$this->assertSame( 'John Q Public', $station['full_name'] );
		$this->assertSame( 'Colorado Springs, CO', $station['location'] );
		$this->assertSame( 'DM78qt', $station['grid'] );
		$this->assertSame( 'callook', $station['source'] );
	}

	public function test_last_first_name_order() {
		$station = ARNL_Callsign_Lookup::parse_callook( $this->response( array( 'name' => "O'BRIEN, MARY-KATE" ) ) );
		$this->assertSame( 'Mary-Kate', $station['name'] );
		$this->assertSame( "Mary-Kate O'Brien", $station['full_name'] );
	}

	public function test_club_keeps_full_name() {
		$station = ARNL_Callsign_Lookup::parse_callook(
			$this->response(
				array(
					'type'    => 'CLUB',
					'current' => array( 'callsign' => 'W1AW' ),
					'name'    => 'ARRL HQ OPERATORS CLUB',
					'address' => array( 'line2' => 'NEWINGTON, CT 06111' ),
				)
			)
		);
		$this->assertSame( 'Arrl Hq Operators Club', $station['name'] );
		$this->assertSame( 'Newington, CT', $station['location'] );
	}

	public function test_zip_plus_four_and_missing_state() {
		$this->assertSame( 'Denver, CO', ARNL_Callsign_Lookup::parse_callook( $this->response( array( 'address' => array( 'line2' => 'DENVER, CO 80202-1234' ) ) ) )['location'] );
		$this->assertSame( 'Apo', ARNL_Callsign_Lookup::parse_callook( $this->response( array( 'address' => array( 'line2' => 'APO 09001' ) ) ) )['location'] );
	}

	public function test_invalid_and_malformed_responses() {
		$this->assertNull( ARNL_Callsign_Lookup::parse_callook( array( 'status' => 'INVALID' ) ) );
		$this->assertNull( ARNL_Callsign_Lookup::parse_callook( array( 'status' => 'UPDATING' ) ) );
		$this->assertNull( ARNL_Callsign_Lookup::parse_callook( null ) );
		$this->assertNull( ARNL_Callsign_Lookup::parse_callook( $this->response( array( 'current' => array( 'callsign' => '' ) ) ) ) );
	}

	public function test_title_case_keeps_mixed_case() {
		$this->assertSame( 'McDonald', ARNL_Callsign_Lookup::title_case( 'McDonald' ) );
		$this->assertSame( 'Fort Collins', ARNL_Callsign_Lookup::title_case( 'FORT COLLINS' ) );
	}
}
