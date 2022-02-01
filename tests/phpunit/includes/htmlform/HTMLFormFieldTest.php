<?php

/**
 * @covers HTMLFormField
 */
class HTMLFormFieldTest extends PHPUnit\Framework\TestCase {

	use MediaWikiCoversValidator;

	/**
	 * @covers HTMLFormField::isHidden
	 * @covers HTMLFormField::isDisabled
	 * @covers HTMLFormField::checkStateRecurse
	 * @covers HTMLFormField::validateCondState
	 * @covers HTMLFormField::getNearestFieldByName
	 * @dataProvider provideCondState
	 */
	public function testCondState( $fieldInfo, $requestData, $callback, $exception = null ) {
		if ( $exception ) {
			$this->expectException( MWException::class );
			$this->expectExceptionMessageMatches( $exception );
		}
		$request = new FauxRequest( $requestData, true );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$form = HTMLForm::factory( 'ooui', wfArrayPlus2d( $fieldInfo, [
			'check1' => [ 'type' => 'check' ],
			'check2' => [ 'type' => 'check', 'invert' => true ],
			'select1' => [ 'type' => 'select', 'options' => [ 'a' => 'a', 'b' => 'b', 'c' => 'c' ], 'default' => 'b' ],
			'text1' => [ 'type' => 'text' ],
		] ), $context );
		$form->setTitle( Title::newFromText( 'Main Page' ) )->setSubmitCallback( static function () {
			return true;
		} )->prepareForm();
		$status = $form->trySubmit();
		$this->assertTrue( $status );

		$callback( $form, $form->mFieldData );
	}

	public function provideCondState() {
		yield 'Field hidden if "check" field is checked' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'check1', '1' ] ],
			],
			'requestData' => [
				'wpcheck1' => '1',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden if "check" field is not checked' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'check1', '' ] ],
			],
			'requestData' => [],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field not hidden if "check" field is not checked' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'check1', '1' ] ],
			],
			'requestData' => [],
			'callback' => function ( $form, $fieldData ) {
				$this->assertFalse( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden if "check" field (invert) is checked' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'check2', '1' ] ],
			],
			'requestData' => [
				'wpcheck2' => '1',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden if "check" field (invert) is not checked' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'check2', '1' ] ],
			],
			'requestData' => [],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field not hidden if "check" field (invert) is checked' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '!==', 'check2', '1' ] ],
			],
			'requestData' => [
				'wpcheck2' => '1',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertFalse( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden if "select" field has value' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'select1', 'a' ] ],
			],
			'requestData' => [
				'wpselect1' => 'a',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden if "text" field has value' => [
			'fieldInfo' => [
				'select1' => [ 'hide-if' => [ '===', 'text1', 'hello' ] ],
			],
			'requestData' => [
				'wptext1' => 'hello',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'select1' )->isHidden( $fieldData ) );
			}
		];

		yield 'Field hidden using AND conditions' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ 'AND',
					[ '===', 'check1', '1' ],
					[ '===', 'select1', 'a' ]
				] ],
			],
			'requestData' => [
				'wpcheck1' => '1',
				'wpselect1' => 'a',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden using OR conditions' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ 'OR',
					[ '===', 'check1', '1' ],
					[ '===', 'select1', 'a' ]
				] ],
			],
			'requestData' => [
				'wpcheck1' => '1',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden using NAND conditions' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ 'NAND',
					[ '===', 'check1', '1' ],
					[ '===', 'select1', 'a' ]
				] ],
			],
			'requestData' => [
				'wpcheck1' => '1',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden using NOR conditions' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ 'NOR',
					[ '===', 'check1', '1' ],
					[ '===', 'select1', 'a' ]
				] ],
			],
			'requestData' => [],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];
		yield 'Field hidden using complex conditions' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ 'OR',
					[ 'NOT', [ 'AND',
						[ '===', 'check1', '1' ],
						[ '===', 'check2', '1' ]
					] ],
					[ '===', 'select1', 'a' ]
				] ],
			],
			'requestData' => [],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isHidden( $fieldData ) );
			}
		];

		yield 'Invalid conditional specification (unsupported)' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '>', 'test1', '10' ] ],
			],
			'requestData' => [],
			'callback' => null,
			'exception' => '/Unknown operation/',
		];
		yield 'Invalid conditional specification (NOT)' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ 'NOT', '===', 'check1', '1' ] ],
			],
			'requestData' => [],
			'callback' => null,
			'exception' => '/NOT takes exactly one parameter/',
		];
		yield 'Invalid conditional specification (AND/OR/NAND/NOR)' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ 'AND', '===', 'check1', '1' ] ],
			],
			'requestData' => [],
			'callback' => null,
			'exception' => '/Expected array, found string/',
		];
		yield 'Invalid conditional specification (===/!==) 1' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'check1' ] ],
			],
			'requestData' => [],
			'callback' => null,
			'exception' => '/=== takes exactly two parameters/',
		];
		yield 'Invalid conditional specification (===/!==) 2' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', [ '===', 'check1', '1' ], '1' ] ],
			],
			'requestData' => [],
			'callback' => null,
			'exception' => '/Parameters for === must be strings/',
		];

		yield 'Field disabled if "check" field is checked' => [
			'fieldInfo' => [
				'text1' => [ 'disable-if' => [ '===', 'check1', '1' ] ],
			],
			'requestData' => [
				'wpcheck1' => '1',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isDisabled( $fieldData ) );
			}
		];
		yield 'Field disabled if hidden' => [
			'fieldInfo' => [
				'text1' => [ 'hide-if' => [ '===', 'check1', '1' ] ],
			],
			'requestData' => [
				'wpcheck1' => '1',
			],
			'callback' => function ( $form, $fieldData ) {
				$this->assertTrue( $form->getField( 'text1' )->isDisabled( $fieldData ) );
			}
		];
	}
}
