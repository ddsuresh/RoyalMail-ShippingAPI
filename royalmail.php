<?php
	ini_set('soap.wsdl_cache_enabled', 0);
	ini_set('soap.wsdl_cache_ttl', 900);
	ini_set('default_socket_timeout', 15);

	define("rm_username","<username>");
	define("rm_password","<password>");
	define("rm_appid","<app_id>");
	//define("rm_endpoint","https://api.royalmail.net/shipping/v2/?wsdl");
	define("rm_endpoint", dirname(__FILE__) . "/Shipping API V2 (SOAP).wsdl"); // use local copy of wsdl to speed up the performance
	define("rm_clientid", "<client_id>");
	define("rm_clientsecret","<client_secret>");

	function makeRequest() {
		$options = array(
			'uri'=>'http://schemas.xmlsoap.org/soap/envelope/',
			'style'=>SOAP_RPC,
			'use'=>SOAP_ENCODED,
			'soap_version'=>SOAP_1_1,
			'cache_wsdl'=>WSDL_CACHE_NONE,
			'connection_timeout'=>15,
			'trace'=>true,
			'encoding'=>'UTF-8',
			'exceptions'=>true,
			'stream_context' => stream_context_create(
				[
					'http' =>
						[
							'header'           => implode(
								"\r\n",
								[
									'Accept: application/soap+xml',
									'X-IBM-Client-Id: ' . rm_clientid,
									'X-IBM-Client-Secret: ' . rm_clientsecret,
								]
							),
						],
				])
		);

		$client = new SoapClient(rm_endpoint, $options);

		$created = gmdate('Y-m-d\TH:i:s\Z');
		$nonce = mt_rand();
		$nonce_date_pwd = pack("A*",$nonce) . pack("A*",$created) . pack("H*", sha1(rm_password));
		$passwordDigest = base64_encode(pack('H*',sha1($nonce_date_pwd)));
		$ENCODEDNONCE = base64_encode($nonce);

		$HeaderObjectXML  = '<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
							  xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
				   <wsse:UsernameToken wsu:Id="UsernameToken-xxxxxx">
					  <wsse:Username>'.rm_username.'</wsse:Username>
					  <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">'.$passwordDigest.'</wsse:Password>
					  <wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'.$ENCODEDNONCE.'</wsse:Nonce>
					  <wsu:Created>'.$created.'</wsu:Created>
				   </wsse:UsernameToken>
			   </wsse:Security>';

				//push the header into soap
		$HeaderObject = new SoapVar( $HeaderObjectXML, XSD_ANYXML );

				//push soap header
		$header = new SoapHeader( 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd', 'Security', $HeaderObject );
		$client->__setSoapHeaders($header);

		return $client;
	}

	function getError( $output ) {
		$errors = array();
		if (is_array($output->integrationFooter->errors->error)) {
			foreach($output->integrationFooter->errors->error as $error) {
				$errors[] = $error->errorCode . " - " . $error->errorDescription;
			}
		}
		else {
			$errors[] = $output->integrationFooter->errors->error->errorCode . " - " . $output->integrationFooter->errors->error->errorDescription;
		}
		return implode("\n", $errors);
	}

	function getIntegrationHeader() {
		$integrationHeader = new stdClass();
		$integrationHeader->identification = new stdClass();
		$integrationHeader->identification->applicationId = rm_appid;
		$integrationHeader->identification->transactionId = date("YmdHis");

		$integrationHeader->dateTime = (new DateTime())->format(\DateTime::ATOM);
		$integrationHeader->version = '2';

		return $integrationHeader;
	}

	function createShipment() {
		try {
			$client = makeRequest();

			// adding integration header
			$createShipmentRequest = new stdClass();
			$createShipmentRequest->integrationHeader = getIntegrationHeader();

			$requestedShipment = new stdClass();
			$requestedShipment->shipmentType = new stdClass();
			$requestedShipment->shipmentType->code = 'Delivery';
			$requestedShipment->serviceOccurrence = '1';
			$requestedShipment->serviceType = new stdClass();
			$requestedShipment->serviceType->code = 'D';

			$requestedShipment->serviceOffering = new stdClass();
			$requestedShipment->serviceOffering->serviceOfferingCode = new stdClass();
			$requestedShipment->serviceOffering->serviceOfferingCode->code = 'SD1';

			$requestedShipment->serviceFormat = new stdClass();
			$requestedShipment->serviceFormat->serviceFormatCode = new stdClass();
			$requestedShipment->serviceFormat->serviceFormatCode->code = 'P';

			$requestedShipment->shippingDate = date("Y-m-d", strtotime("+1 day"));

			// adding receipient contact information
			$recipientContact = new stdClass();
			$recipientContact->name = 'Suresh N';
			$recipientContact->telephoneNumber = new stdClass();
			$recipientContact->telephoneNumber->telephoneNumber = '1234567890';
			$recipientContact->electronicAddress = new stdClass();
			$recipientContact->electronicAddress->electronicAddress = 'xyz@yahoo.com';

			$requestedShipment->recipientContact = $recipientContact;

			// adding receipent address details
			$recipientAddress = new stdClass();
			$recipientAddress->buildingName = 'Testing Apartments';
			$recipientAddress->addressLine1 = '44-46 Morningside Road';
			$recipientAddress->postTown = 'Edinburgh';
			$recipientAddress->postcode = 'EH10 4BF';
			$recipientAddress->country = new stdClass();
			$recipientAddress->country->countryCode = new stdClass();
			$recipientAddress->country->countryCode->code = 'GB';

			$requestedShipment->recipientAddress = $recipientAddress;

			// product items
			$items = new stdClass();

			$item = new stdClass();
			$item->numberOfItems = 1;

			$item->weight = new stdClass();
			$item->weight->unitOfMeasure = new stdClass();
			$item->weight->unitOfMeasure->unitOfMeasureCode = new stdClass();
			$item->weight->unitOfMeasure->unitOfMeasureCode->code = 'g';
			$item->weight->value = '200';

			$offlineShipments1 = new stdClass();
			//$offlineShipments1->itemID = '2000001';
			$offlineShipments1->status = new stdClass();
			$offlineShipments1->status->status = new stdClass();
			$offlineShipments1->status->status->statusCode = '';
			$offlineShipments1->status->validFrom = (new DateTime())->format(\DateTime::ATOM);

			$item->offlineShipments = array();
			$item->offlineShipments[] = $offlineShipments1;

			$items->item = $item;

			$requestedShipment->items = $items;

			$createShipmentRequest->requestedShipment = $requestedShipment;

			$output = $client->createShipment( $createShipmentRequest );

			if (isset($output->completedShipmentInfo->allCompletedShipments->completedShipments->shipments->shipmentNumber)) {
				return $output->completedShipmentInfo->allCompletedShipments->completedShipments->shipments->shipmentNumber;
			}
			else {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	function printLabel( $shippingNumber ) {
		try {
			$client = makeRequest();

			// adding integration header
			$printLabelRequest = new stdClass();
			$printLabelRequest->integrationHeader = getIntegrationHeader();

			$printLabelRequest->shipmentNumber = $shippingNumber;

			$output = $client->printLabel( $printLabelRequest );

			if (isset($output->label)) {
				return array('label' => $output->label, 'format' => $output->outputFormat);
			}
			else if (isset($output->integrationFooter->errors)) {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	function createManifest() {
		try {
			$client = makeRequest();

			// adding integration header
			$createManifestRequest = new stdClass();
			$createManifestRequest->integrationHeader = getIntegrationHeader();

			$createManifestRequest->serviceOffering = new stdClass();
			$createManifestRequest->serviceOffering->serviceOfferingCode = new stdClass();
			$createManifestRequest->serviceOffering->serviceOfferingCode->code = 'SD1';

			$createManifestRequest->yourDescription = 'Creating Manifest of the day';
			$createManifestRequest->yourReference = 'creating-manifest-of-the-day';

			$output = $client->createManifest( $createManifestRequest );

			if (isset($output->completedManifests)) {
				return $output->completedManifests;
			}
			else if (isset($output->integrationFooter->errors)) {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	function printManifest( $batchNumber ) {
		try {
			$client = makeRequest();

			// adding integration header
			$printManifestRequest = new stdClass();
			$printManifestRequest->integrationHeader = getIntegrationHeader();

			$printManifestRequest->manifestBatchNumber = $batchNumber;

			$output = $client->printManifest( $printManifestRequest );

			if (isset($output->manifest)) {
				return $output->manifest;
			}
			else if (isset($output->integrationFooter->errors)) {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	function updateShipment( $shippingNumber ) {

		$client = makeRequest();

		// adding integration header
		$updateShipmentRequest = new stdClass();
		$updateShipmentRequest->integrationHeader = getIntegrationHeader();

		$requestedShipment = new stdClass();

		// updating receipient contact information
		$recipientContact = new stdClass();
		$recipientContact->name = 'Suresh N';
		$recipientContact->telephoneNumber = new stdClass();
		$recipientContact->telephoneNumber->telephoneNumber = '9876543210';
		$recipientContact->electronicAddress = new stdClass();
		$recipientContact->electronicAddress->electronicAddress = 'xyz@yahoo.com';

		$requestedShipment->recipientContact = $recipientContact;

		// updating receipent address details
		$recipientAddress = new stdClass();
		$recipientAddress->buildingName = 'Testing Apartments 2';
		$recipientAddress->addressLine1 = '44-46 Morningside Road';
		$recipientAddress->postTown = 'Edinburgh';
		$recipientAddress->postcode = 'EH10 4BF';
		$recipientAddress->country = new stdClass();
		$recipientAddress->country->countryCode = new stdClass();
		$recipientAddress->country->countryCode->code = 'GB';

		$requestedShipment->recipientAddress = $recipientAddress;

		$updateShipmentRequest->shipmentNumber = $shippingNumber;
		$updateShipmentRequest->requestedShipment = $requestedShipment;

		$output = $client->updateShipment( $updateShipmentRequest );

		if (isset($output->shipmentNumber)) {
			return $output->shipmentNumber;
		}
		else if (isset($output->integrationFooter->errors)) {
			return getError( $output );
		}

		return $output;
	}

	function cancelShipment( $shippingNumber ) {
		try {
			$client = makeRequest();

			$cancelShipmentRequest = new stdClass();
			$cancelShipmentRequest->integrationHeader = getIntegrationHeader();
			$cancelShipmentRequest->cancelShipments = new stdClass();
			$cancelShipmentRequest->cancelShipments->shipmentNumber = $shippingNumber;

			$output = $client->cancelShipment( $cancelShipmentRequest );

			if (isset($output->label)) {
				return $output->label;
			}
			else if (isset($output->integrationFooter->errors)) {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	function printDocument( $shippingNumber ) {
		try {
			$client = makeRequest();

			// adding integration header
			$printDocumentRequest = new stdClass();
			$printDocumentRequest->integrationHeader = getIntegrationHeader();

			$printDocumentRequest->shipmentNumber = $shippingNumber;
			$printDocumentRequest->documentName = 'CN22';
			$printDocumentRequest->documentCopies = '1';

			$output = $client->printDocument( $printDocumentRequest );

			if (isset($output->label)) {
				return $output->label;
			}
			else if (isset($output->integrationFooter->errors)) {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	function request1DRanges() {
		try {
			$client = makeRequest();

			// adding integration header
			$request1DRangesRequest = new stdClass();
			$request1DRangesRequest->integrationHeader = getIntegrationHeader();

			$serviceReference = new stdClass();
			$serviceReference->serviceOccurrence = '1';
			$serviceReference->serviceOffering = new stdClass();
			$serviceReference->serviceOffering->serviceOfferingCode = new stdClass();
			$serviceReference->serviceOffering->serviceOfferingCode->code = 'SD1';

			$serviceReference->serviceType = new stdClass();
			$serviceReference->serviceType->code = 'I';

			$request1DRangesRequest->serviceReferences = new stdClass();
			$request1DRangesRequest->serviceReferences->serviceReference = $serviceReference;

			$output = $client->request1DRanges( $request1DRangesRequest );

			if (isset($output->serviceRanges)) {
				return $output->serviceRanges;
			}
			else if (isset($output->integrationFooter->errors)) {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	function request2DItemIDRange() {
		try {
			$client = makeRequest();

			// adding integration header
			$request2DItemIDRangeRequest = new stdClass();
			$request2DItemIDRangeRequest->integrationHeader = getIntegrationHeader();

			$output = $client->request2DItemIDRange( $request2DItemIDRangeRequest );

			if (isset($output->itemIDRange)) {
				return $output->itemIDRange;
			}
			else if (isset($output->integrationFooter->errors)) {
				return getError( $output );
			}

			return $output;
		}
		catch(Exception $e) {
			return $e->__toString();
		}
	}

	//var_dump(createShipment());
	//var_dump(updateShipment('TTT005181300GB'));
	//var_dump(printDocument('TTT005181300GB'));

	//$pdfContent = printLabel('TTT005181300GB');
	//file_put_contents(dirname(__FILE__) . '/TTT005181300GB.' . $pdfContent['format'], $pdfContent['label']);

	//var_dump(createManifest());

	//$manifestContent = printManifest('2');
	//file_put_contents(dirname(__FILE__) . '/TTT005181300GB_manifest.pdf', $manifestContent);

	//var_dump(request1DRanges());

	//var_dump(request2DItemIDRange());

	var_dump(cancelShipment('TTT005181300GB'));