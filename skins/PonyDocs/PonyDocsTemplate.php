<?php

/**
 * @todo document
 * @ingroup Skins
 */
class PonyDocsTemplate extends QuickTemplate {
	var $skin;

	/**
	 * This lets you map full titles or namespaces to specific PHP template files and prep methods.
	 * The special '0' index is the default if not found. Prefix title mappings with 'T:' and namespace mappings with 'NS:'. 
	 * Currently if inside a namespace it will ignore any title mappings (i.e., either it calls the NS:namespace or the default).
	 *
	 * @var array
	 */
	private $_methodMappings = array(
		0 => array( 'prep' => '', 'tpl' => 'nsDefault' ),
		'T:Documentation' => array( 'prep' => 'prepareDocumentation', 'tpl' => 'nsDocumentation' ),
		'NS:Documentation' => array( 'prep' => 'prepareDocumentation', 'tpl' => 'nsDocumentation' )
	);


	function execute() {
		global $action, $IP, $wgArticlePath, $wgContLang, $wgExtraNamespaces, $wgRequest, $wgRevision, $wgTitle, $wgUser;

		PonyDocsProduct::LoadProducts();
		$this->data['selectedProduct'] = PonyDocsProduct::GetSelectedProduct();
		PonyDocsProductVersion::LoadVersionsForProduct( $this->data['selectedProduct'] );
		PonyDocsProductManual::LoadManualsForProduct( $this->data['selectedProduct'] );

		$ponydocs = PonyDocsWiki::getInstance();

		$this->data['products'] = $ponydocs->getProductsForTemplate();
		$this->data['versions'] = $ponydocs->getVersionsForProduct( $this->data['selectedProduct'] );
		$this->data['namespaces'] = $wgExtraNamespaces;
		$this->data['selectedVersion'] = PonyDocsProductVersion::GetSelectedVersion( $this->data['selectedProduct'] );
		$this->data['pVersion'] =
			PonyDocsProductVersion::GetVersionByName( $this->data['selectedProduct'], $this->data['selectedVersion'] );
		if ( PONYDOCS_DEBUG ) {
			error_log( "DEBUG [" . __METHOD__ . "] selected product/version is set to " . $this->data['selectedProduct'] . "/"
				. $this->data['selectedVersion'] );
		}
		$this->data['versionurl'] = $this->data['wgScript'] . '?title=' . $this->data['thispage'] . '&action=changeversion';

		$this->skin = $this->data['skin'];
		// TODO remove this, and replace elsewhere (template files mostly) with $this->skin
		$skin = $this->data['skin'];

		if ( $this->skin->getTitle() ) {
			$this->data['canonicalURI'] = $this->skin->getTitle()->getFullURL();
		}

		$action = $wgRequest->getText( 'action' );

		// Suppress warnings to prevent notices about missing indexes in $this->data
		wfSuppressWarnings();

		/**
		 * When displaying a page we output header.php, then a sub-template, and then footer.php. The namespace
		 * which we are in determines the sub-template, which is named 'ns<Namespace>'. It defaults to our
		 * nsDefault.php template. 
		 */
		$this->data['namespace'] = $wgContLang->getNsText($wgTitle->getNamespace());
		$idx = $this->data['namespace'] ? "NS:{$this->data['namespace']}" : 'T:' . $wgTitle->__toString();
		if ( !isset( $this->_methodMappings[$idx] ) ) {
			$idx = 0;
		}

		$inDocumentation = FALSE;
		if ( $this->data['namespace'] == PONYDOCS_DOCUMENTATION_NAMESPACE_NAME
			|| $wgTitle->__toString() == PONYDOCS_DOCUMENTATION_NAMESPACE_NAME
			|| preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':/', $wgTitle->__toString() ) ) {
			$inDocumentation = TRUE;
			$this->prepareDocumentation();
		}
		$this->data['versions'] = $ponydocs->getVersionsForProduct( $this->data['selectedProduct'] );

		$this->html( 'headelement' ); ?>
		<script type="text/javascript">
			function AjaxChangeProduct_callback( o ) {
				document.getElementById( 'docsProductSelect' ).disabled = true;
				var s = new String( o.responseText );
				document.getElementById( 'docsProductSelect' ).disabled = false;
				window.location.href = s;
			}

			function AjaxChangeProduct() {
				var productIndex = document.getElementById( 'docsProductSelect' ).selectedIndex;
				var product = document.getElementById( 'docsProductSelect' )[productIndex].value;
				var title = '<?php Xml::escapeJsString($this->data['thispage']); ?>';
				sajax_do_call( 'efPonyDocsAjaxChangeProduct', [product,title], AjaxChangeProduct_callback );
			}

			function AjaxChangeVersion_callback( o ) {
				document.getElementById( 'docsVersionSelect' ).disabled = true;
				var s = new String( o.responseText );
				document.getElementById( 'docsVersionSelect' ).disabled = false;
				window.location.href = s;
			}

			function AjaxChangeVersion() {
				var productIndex = document.getElementById( 'docsProductSelect' ).selectedIndex;
				var product = document.getElementById( 'docsProductSelect' )[productIndex].value;
				var versionIndex = document.getElementById( 'docsVersionSelect' ).selectedIndex;
				var version = document.getElementById( 'docsVersionSelect' )[versionIndex].value;
				var title = '<?php Xml::escapeJsString($this->data['thispage']); ?>';
				sajax_do_call( 'efPonyDocsAjaxChangeVersion', [product,version,title], AjaxChangeVersion_callback );
			}

			function changeManual(){
				var url = $( "#docsManualSelect" ).val();
				if ( url != "" ){
					window.location.href = url;
				}
			}
		</script>
		<div id="globalWrapper">

			<div id="column-content">
				<div id="content" <?php $this->html( "specialpageattributes" ) ?>>
					<a id="top"></a>
					<?php
					if ( $this->data['sitenotice'] ) { ?>
						<div id="siteNotice"><?php $this->html( 'sitenotice' ) ?></div>
						<?php
					} ?>

					<?php
					if ( !$inDocumentation ) { ?>
						<h1 id="firstHeading" class="firstHeading"><?php $this->html( 'title' ); ?></h1>
						<?php
					} ?>
					<div id="bodyContent">
						<h3 id="siteSub"><?php $this->msg( 'tagline' ) ?></h3>
						<div id="contentSub"<?php $this->html( 'userlangattributes' ) ?>><?php $this->html( 'subtitle' ) ?></div>
						<?php
						if ( $this->data['undelete'] ) { ?>
							<div id="contentSub2"><?php $this->html( 'undelete' ) ?></div>
							<?php
						}
						if ( $this->data['newtalk'] ) { ?>
							<div class="usermessage"><?php $this->html( 'newtalk' )  ?></div>
							<?php
						}
						if ( $this->data['showjumplinks'] ) { ?>
							<div id="jump-to-nav">
								<?php $this->msg( 'jumpto' ) ?> <a href="#column-one"><?php $this->msg('jumptonavigation') ?></a>,
								<a href="#searchInput"><?php $this->msg( 'jumptosearch' ) ?></a>
							</div>
							<?php
						}
						// Version group message, if any
						if ( $this->data['versionGroupMessage'] !== null ) { ?>
							<div class="affectedVersions
								<?php echo implode( " ", $this->data['versionclasses'] ); ?>">
								<p class="bannerVersion">
									<?php echo $this->data['versionGroupMessage'];?>
								</p>
							</div>
							<?php
						} ?>
						<!-- start content -->
						<?php
						$this->html( 'bodytext' );
						if ( $this->data['catlinks'] ) {
							$this->html('catlinks');
						} ?>
						<!-- end content -->
						<?php 
						if ( $this->data['dataAfterContent'] ) {
							$this->html( 'dataAfterContent' );
						} ?>
						<div class="visualClear"></div>
					</div>
				</div>
			</div>
			<div id="column-one"<?php $this->html('userlangattributes') ?>>
				<div id="p-cactions" class="portlet">
					<h5><?php $this->msg('views') ?></h5>
					<div class="pBody">
						<ul><?php
							foreach($this->data['content_actions'] as $key => $tab) {
								echo '
							 <li id="' . Sanitizer::escapeId( "ca-$key" ) . '"';
								if( $tab['class'] ) {
									echo ' class="'.htmlspecialchars($tab['class']).'"';
								}
								echo '><a href="'.htmlspecialchars($tab['href']).'"';
								# We don't want to give the watch tab an accesskey if the
								# page is being edited, because that conflicts with the
								# accesskey on the watch checkbox. We also don't want to
								# give the edit tab an accesskey, because that's fairly su-
								# perfluous and conflicts with an accesskey (Ctrl-E) often
								# used for editing in Safari.
								if( in_array( $action, array( 'edit', 'submit' ) )
								&& in_array( $key, array( 'edit', 'watch', 'unwatch' ))) {
									echo $skin->tooltip( "ca-$key" );
								} else {
									echo Xml::expandAttributes( $skin->tooltipAndAccesskeyAttribs( "ca-$key" ) );
								}
								echo '>'.htmlspecialchars($tab['text']).'</a></li>';
							} ?>

						</ul>
					</div>
				</div>
				<div class="portlet" id="p-personal">
					<h5><?php $this->msg( 'personaltools' ) ?></h5>
					<div class="pBody">
						<ul<?php $this->html( 'userlangattributes' ) ?>>
						<?php
						foreach ( $this->data['personal_urls'] as $key => $item ) { ?>
							<li id="<?php echo Sanitizer::escapeId( "pt-$key" ) ?>"
							<?php
							if ( $item['active'] ) { ?>
								class="active"
								<?php
							} ?>>
								<a href="<?php echo htmlspecialchars( $item['href'] ) ?>"
									<?php echo Xml::expandAttributes( $skin->tooltipAndAccesskeyAttribs( 'pt-'.$key ) );
									if ( !empty($item['class'] ) ) { ?>
										class="<?php echo htmlspecialchars( $item['class'] ) ?>"
										<?php
									} ?>>
									<?php echo htmlspecialchars( $item['text'] ) ?>
								</a>
							</li>
							<?php
						} ?>
						</ul>
					</div>
				</div>
				<div class="portlet" id="p-logo">
					<a style="background-image: url(<?php $this->text( 'logopath' ) ?>);" 
					   href="<?php echo htmlspecialchars( $this->data['nav_urls']['mainpage']['href'] )?>"
						<?php echo Xml::expandAttributes( $skin->tooltipAndAccesskeyAttribs( 'p-logo' ) ) ?>></a><br />
				</div>
				<script type="<?php $this->text( 'jsmimetype' ) ?>">
					if ( window.isMSIE55 ) {
						fixalpha();
					} 
				</script>
				<div id="p-documentation" class="portlet">
					<h5>documentation</h5>
					<div id="documentationBody" class="pBody">
						<?php
						if ( !count( $this->data['products'] ) ) { ?>
							<p>No products defined.</p>
							<?php
						} else { ?>
							<div class="product">
								<label for='docsProductSelect' class="navlabels">Product:&nbsp;</label><br />
								<select id="docsProductSelect" name="selectedProduct" onChange="AjaxChangeProduct();">
									<?php $this->hierarchicalProductSelect(); ?>
								</select>
							</div>
							<?php
								$versions = PonyDocsProductVersion::GetVersions( $this->data['selectedProduct'], TRUE );
								if ( !count( $versions ) ) {?>
									<p>No Product Versions Defined.</p>
									<?php
								} else {
									$manuals = PonyDocsProductManual::GetDefinedManuals( $this->data['selectedProduct'], TRUE );
									if ( !count( $manuals ) ) { ?>
										<p>The product manual you requested is not defined, you are not logged in,
											or you do not have the correct permissions to view this content.</p>
										<?php
									} else { ?>
										<p>
											<div class="productVersion">
												<?php
												// do quick manip
												$found = FALSE;
												for ( $i =( count( $this->data['versions'] ) - 1 ); $i >= 0; $i-- ) {
													$this->data['versions'][$i]['label'] = $this->data['versions'][$i]['longName'];
													if ( !$found && $this->data['versions'][$i]['status'] == "released" ) {
														$this->data['versions'][$i]['label'] .= " (latest release)";
														$found = TRUE;
													}
												} ?>
												<label for='docsVersionSelect' class="navlabels">Product version:&nbsp;</label><br />
												<select id="docsVersionSelect" name="selectedVersion" onChange="AjaxChangeVersion();">
													<?php
													foreach ( $this->data['versions'] as $idx => $data ) {
														echo '<option value="' . $data['name'] . '" ';
														if ( !strcmp( $data['name'], $this->data['selectedVersion'] ) ) {
															echo 'selected';
														}
														echo '>' . $data['label'] . '</option>';
													} ?>
												</select>
											</div>
											<div class="productManual">
												<label for="docsManualSelect" class="navlabels">Select manual:&nbsp;</label><br />
												<select id="docsManualSelect" name="selectedManual" onChange="changeManual();">
													<?php
													$navData = PonyDocsExtension::fetchNavDataForVersion( $this->data['selectedProduct'], $this->data['selectedVersion'] );
													print "<option value=''>Pick One...</option>";
													//loop through nav array and look for current URL
													foreach ( $navData as $manual ) {
														$selected = "";
														if ( !strcmp( $this->data['manualname'], $manual['longName'] ) ) {
															$selected = " selected ";
														}
														print "<option value='". $manual['firstUrl'] . "'   $selected>";
														print $manual['longName'];
														print "<!-- categories: {$manual['categories']} -->";
														print '</option>';
													} ?>
												</select>
											</div>
										</p>
										<p>
											<?php
											if ( sizeof($this->data['manualtoc'] ) ) { 
												$pdfLink = str_replace( '$1', '', $wgArticlePath ) . 'index.php?title=' . $wgTitle->__toString() . '&action=pdfbook&version=' .$this->data['selectedVersion'];
												$topicLink = $pdfLink . '&topic=1';
												?>
												<p>
													<a href="<?php echo $pdfLink; ?>">Manual as PDF</a>
													<a href="<?php echo $topicLink; ?>">Topic as PDF</a>
												</p>
												<?php
												$inUL = FALSE;
												$listid = "";
												foreach ( $this->data['manualtoc'] as $idx => $data ) {
													if ( 0 == $data['level'] ) {
														if ( $inUL ) {
															echo '</ul></div>';
															$inUL = FALSE;
														}
														$listid = "list" . $idx;
														echo '<div class="wikiSidebarBox collapsible">';
														echo '<h3>' . $data['text'] . '</h3>';
														echo '<ul>';
														$inUL = TRUE;
													} elseif ( 1 == $data['level'] ) {
														if ( $data['current'] ) {
															echo '<li class="expanded">' . $data['text'] . '</li>';
														} else {
															echo '<li><a href="' . wfUrlencode($data['link']) . '">' . $data['text'] . '</a></li>';
														}
													} else {
														if ( $data['current'] ) {
															echo '<li class="expanded" style="margin-left: 13px;">' . $data['text'] . '</li>';
														} else {
															echo '<li style="margin-left: 13px;"><a href="' . wfUrlencode($data['link']) . '">' . $data['text'] . '</a></li>';
														}
													}
												}
												if ( $inUL ) {
													echo '</ul></div>';
												}
											} ?>
										</p>
										<?php
									}
								}
							} ?>
					</div>
				</div>
				<?php
					$sidebar = $this->data['sidebar'];
					if ( !isset( $sidebar['SEARCH'] ) ) {
						$sidebar['SEARCH'] = TRUE;
					}
					if ( !isset( $sidebar['TOOLBOX'] ) ) {
						$sidebar['TOOLBOX'] = TRUE;
					}
					if ( !isset( $sidebar['LANGUAGES'] ) ) {
						$sidebar['LANGUAGES'] = TRUE;
					}
					foreach ( $sidebar as $boxName => $cont ) {
						if ( $boxName == 'SEARCH' ) {
							$this->searchBox();
						} elseif ( $boxName == 'TOOLBOX' ) {
							$this->toolbox();
						} elseif ( $boxName == 'LANGUAGES' ) {
							$this->languageBox();
						} else {
							$this->customBox( $boxName, $cont );
						}
					} ?>
			</div><!-- end of the left (by default at least) column -->
			<div class="visualClear"></div>
			<div id="footer"<?php $this->html( 'userlangattributes' ) ?>>
				<?php
				if ( $this->data['poweredbyico'] ) { ?>
					<div id="f-poweredbyico"><?php $this->html( 'poweredbyico' ) ?></div>
					<?php
				}
				if ( $this->data['copyrightico'] ) { ?>
					<div id="f-copyrightico"><?php $this->html('copyrightico') ?></div>
					<?php
				}

				// Generate additional footer links
				$footerlinks = array( 'lastmod', 'viewcount', 'numberofwatchingusers', 'credits', 'copyright', 'privacy', 'about',
					'disclaimer', 'tagline', );
				$validFooterLinks = array();
				foreach ( $footerlinks as $aLink ) {
					if ( isset( $this->data[$aLink] ) && $this->data[$aLink] ) {
						$validFooterLinks[] = $aLink;
					}
				}
				if ( count( $validFooterLinks ) > 0 ) { ?>	
					<ul id="f-list">
						<?php
						foreach ( $validFooterLinks as $aLink ) {
							if ( isset( $this->data[$aLink] ) && $this->data[$aLink] ) { ?>
								<li id="<?php echo $aLink ?>"><?php $this->html($aLink) ?></li>
								<?php
							}
						} ?>
					</ul>
					<?php
				} ?>
			</div>
		</div>
		<?php $this->html( 'bottomscripts' ); /* JS call to runBodyOnloadHook */ ?>
		<?php $this->html( 'reporttime' ) ?>
		<?php if ( $this->data['debug'] ) { ?>
			<!-- Debug output: <?php $this->text( 'debug' ); ?> -->
			<?php
		} ?>
	</body>
</html>
		<?php
		wfRestoreWarnings();
	} // end of execute() method

	function searchBox() {
		global $wgUseTwoButtonsSearchForm; ?>
		<div id="p-search" class="portlet">
			<h5><label for="searchInput"><?php $this->msg( 'search' ) ?></label></h5>
			<div id="searchBody" class="pBody">
				<form action="<?php $this->text( 'wgScript' ) ?>" id="searchform">
					<input type='hidden' name="title" value="<?php $this->text( 'searchtitle' ) ?>"/>
					<?php
					echo Html::input( 'search', isset( $this->data['search'] ) ? $this->data['search'] : '', 'search',
						array(
							'id' => 'searchInput',
							'title' => $this->skin->titleAttrib( 'search' ),
							'accesskey' => $this->skin->accesskey( 'search' )
					) ); ?>
					<input type='submit' name="go" class="searchButton" id="searchGoButton"
						   value="<?php $this->msg( 'searcharticle' ) ?>"
							<?php echo Xml::expandAttributes( $this->skin->tooltipAndAccesskeyAttribs( 'search-go' ) ); ?> />
					<?php
					if ( $wgUseTwoButtonsSearchForm ) { ?>&nbsp;
						<input type='submit' name="fulltext" class="searchButton" id="mw-searchButton"
							value="<?php $this->msg( 'searchbutton' ) ?>"
							<?php echo Xml::expandAttributes(
								$this->skin->tooltipAndAccesskeyAttribs( 'search-fulltext' ) ); ?> />
						<?php
					} else { ?>
						<div>
							<a href="<?php $this->text('searchaction') ?>" rel="search">
								<?php $this->msg('powersearch-legend') ?>
							</a>
						</div>
						<?php
					} ?>
				</form>
			</div>
		</div>
		<?php
	}

	function toolbox() { ?>
		<div class="portlet" id="p-tb">
			<h5><?php $this->msg('toolbox') ?></h5>
			<div class="pBody">
				<ul>
					<?php
					if ( $this->data['notspecialpage'] ) { 
						if ( $this->data['nav_urls']['recentchangeslinked'] ) { ?>
							<li id="t-recentchangeslinked">
								<a href="<?php echo htmlspecialchars( $this->data['nav_urls']['recentchangeslinked']['href'] ) ?>"
									<?php echo Xml::expandAttributes(
										$this->skin->tooltipAndAccesskeyAttribs( 't-recentchangeslinked' ) ); ?>>
									<?php $this->msg( 'recentchangeslinked-toolbox' ) ?>
								</a>
							</li>
							<?php
						}
					}
					if ( isset( $this->data['nav_urls']['trackbacklink'] ) && $this->data['nav_urls']['trackbacklink'] ) { ?>
						<li id="t-trackbacklink">
							<a href="<?php echo htmlspecialchars( $this->data['nav_urls']['trackbacklink']['href'] ) ?>"
								<?php echo Xml::expandAttributes(
									$this->skin->tooltipAndAccesskeyAttribs( 't-trackbacklink' ) ); ?>>
								<?php $this->msg('trackbacklink') ?>
							</a>
						</li>
						<?php
					}
					if ( $this->data['feeds'] ) { ?>
						<li id="feedlinks">
							<?php
							foreach ( $this->data['feeds'] as $key => $feed ) { ?>
								<a id="<?php echo Sanitizer::escapeId( "feed-$key" ) ?>" 
									href="<?php echo htmlspecialchars( $feed['href'] ) ?>" rel="alternate"
									type="application/<?php echo $key ?>+xml" class="feedlink"
									<?php echo Xml::expandAttributes(
										$this->skin->tooltipAndAccesskeyAttribs( 'feed-'.$key ) ); ?>>
									<?php echo htmlspecialchars($feed['text'])?>
								</a>&nbsp;
								<?php
							} ?>
						</li>
						<?php
					}
					foreach ( array( 'contributions', 'log', 'blockip', 'emailuser', 'upload', 'specialpages' ) as $special ) {
						if ( $this->data['nav_urls'][$special] ) { ?>
							<li id="t-<?php echo $special ?>">
								<a href="<?php echo htmlspecialchars( $this->data['nav_urls'][$special]['href'] ) ?>"
									<?php echo Xml::expandAttributes(
										$this->skin->tooltipAndAccesskeyAttribs( 't-'.$special ) ); ?>>
									<?php $this->msg( $special ) ?>
								</a>
							</li>
							<?php
						}
					}
					if ( !empty( $this->data['nav_urls']['print']['href'] ) ) { ?>
						<li id="t-print">
							<a href="<?php echo htmlspecialchars( $this->data['nav_urls']['print']['href'] ) ?>" rel="alternate"
								<?php echo Xml::expandAttributes( $this->skin->tooltipAndAccesskeyAttribs( 't-print' ) ); ?>>
								<?php $this->msg( 'printableversion' ) ?>
							</a>
						</li>
						<?php
					}
					if( !empty( $this->data['nav_urls']['permalink']['href'] ) ) { ?>
						<li id="t-permalink">
							<a href="<?php echo htmlspecialchars( $this->data['nav_urls']['permalink']['href'] ) ?>"
								<?php echo Xml::expandAttributes( $this->skin->tooltipAndAccesskeyAttribs( 't-permalink' ) ); ?>>
								<?php $this->msg('permalink') ?>
							</a>
						</li>
						<?php
					} elseif ( $this->data['nav_urls']['permalink']['href'] === '' ) { ?>
						<li id="t-ispermalink"<?php echo $this->skin->tooltip( 't-ispermalink' ) ?>>
							<?php $this->msg( 'permalink' ) ?>
						</li>
						<?php
					}
					wfRunHooks( 'PonyDocsTemplateToolboxEnd', array( &$this ) );
					wfRunHooks( 'SkinTemplateToolboxEnd', array( &$this ) ); ?>
				</ul>
			</div>
		</div>
		<?php
	}

	function languageBox() {
		if ( $this->data['language_urls'] ) { ?>
			<div id="p-lang" class="portlet">
				<h5<?php $this->html( 'userlangattributes' ) ?>><?php $this->msg('otherlanguages') ?></h5>
				<div class="pBody">
					<ul>
						<?php
						foreach ( $this->data['language_urls'] as $langlink ) { ?>
							<li class="<?php echo htmlspecialchars( $langlink['class'] )?>">
								<a href="<?php echo htmlspecialchars($langlink['href']) ?>">
									<?php echo $langlink['text'] ?>
								</a>
							</li>
							<?php
						} ?>
					</ul>
				</div>
			</div>
			<?php
		}
	}

	function customBox( $bar, $cont ) { ?>
		<div class='generated-sidebar portlet' id='<?php echo Sanitizer::escapeId( "p-$bar" ) ?>'<?php echo $this->skin->tooltip('p-'.$bar) ?>>
			<h5>
				<?php
				$out = wfMsg( $bar );
				if ( wfEmptyMsg ( $bar, $out ) ) {
					echo htmlspecialchars( $bar );
				} else {
					echo htmlspecialchars( $out );
				} ?>
			</h5>
			<div class='pBody'>
				<?php
				if ( is_array( $cont ) ) { ?>
					<ul>
						<?php
						foreach ( $cont as $key => $val ) { ?>
							<li id="<?php echo Sanitizer::escapeId( $val['id'] ) ?>"
								<?php
								if ( $val['active'] ) { ?> 
									class="active" <?php
								} ?>>
								<a href="<?php echo htmlspecialchars( $val['href'] ) ?>"
									<?php echo Xml::expandAttributes( $this->skin->tooltipAndAccesskeyAttribs( $val['id'] ) ); ?>>
									<?php echo htmlspecialchars( $val['text'] ) ?>
								</a>
							</li>
							<?php
						} ?>
					</ul>
					<?php
				} else {
					# allow raw HTML block to be defined by extensions
					print $cont;
				} ?>
			</div>
		</div>
		<?php
	}

	public function prepareDocumentation() {
		global $wgOut, $wgParser, $wgScriptPath, $wgTitle, $wgUser;
		/**
		 * We need a lot of stuff from our PonyDocs extension!
		 */
		$ponydocs = PonyDocsWiki::getInstance();
		$this->data['manuals'] = $ponydocs->getManualsForProduct( $this->data['selectedProduct'] );

		/**
		 * Adjust content actions as needed, such as add 'view all' link.
		 */
		$this->contentActions();
		$this->navURLS();

		/**
		 * Possible topic syntax we must handle:
		 * 
		 * Documentation:<topic> *Which may include a version tag at the end, we don't care about this.
		 * Documentation:<productShortName>:<manualShortName>:<topic>:<version>
		 * Documentation:<productShortName>:<manualShortName>
		 */

		/**
		 * Based on the name; i.e. 'Documentation:Product:Manual:Topic' we need to parse it out and store the manual name and
		 * the topic name as parameters. We store manual in 'manualname' and topic in 'topicname'. Special handling
		 * needs to be done for versions and TOC?
		 *
		 * 	0=NS (Documentation)
		 *  1=Product (Short name)
		 *  2=Manual (Short name)
		 *  3=Topic
		 *  4=Version
		 */
		$pManual = null;
		$pieces = explode( ':', $wgTitle->__toString() );
		$helpClass = '';

		/**
		 * This isn't a specific topic+version -- handle appropriately.
		 */
		if ( sizeof( $pieces ) < 4 ) {
			if ( !strcmp( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $this->data['selectedProduct']
				. PONYDOCS_PRODUCTVERSION_SUFFIX,
				$wgTitle->__toString() ) ) {
				$this->data['titletext'] = 'Versions Management - '.$this->data['selectedProduct'];
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>* Use {{#version:versionShortName|status|versionLongName}} to define a new version,'
					. ' where status is released, unreleased, or preview.'
					. ' Valid chars in version short name are A-Z, 0-9, period, comma, and dash.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>* Use {{#versiongroup:name|message}} to set a banner'
					. ' message that will appear on every topic in every version following the versiongroup.'
					. ' End a version group with an empty tag {{#versiongroup:}}'
					. '</i></span>');
			} elseif ( !strcmp( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $this->data['selectedProduct']
				. PONYDOCS_PRODUCTMANUAL_SUFFIX,
				$wgTitle->__toString() ) ) {
				$this->data['titletext'] = 'Manuals Management - '.$this->data['selectedProduct'];
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Use {{#manual:manualShortName|displayName|categories}} to define a new manual.' );
				$wgOut->addHTML('<br><span class="' . $helpClass . '"><i>'
					. '* Prepend manual short name with ' . PONYDOCS_PRODUCT_STATIC_PREFIX. ' to define a static manual.'
					. '</i></span>');
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* If you omit display name, the short name will be used in links.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* There should not be any text before {{#manual:manualShortName|displayName|categories}}. If you add any text before {{#manual:manualShortName|displayName|categories}}, things will break.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* Categories is a comma-separated list of categories</i></span>' );
			} elseif ( !strcmp( PONYDOCS_DOCUMENTATION_PRODUCTS_TITLE, $wgTitle->__toString() ) ) {
				$this->data['titletext'] = 'Products Management';
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Use {{#product:productShortName|displayName|description|parent|categories}} to define a new product.'
					. '</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Prepend product short name with ' . PONYDOCS_PRODUCT_STATIC_PREFIX . ' to define a static product.'
					. '</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* displayName, description, parent, and categories can be left empty.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* If you leave displayName empty, productShortName will be used in links.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* Categories is a comma-separated list of categories.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* Each product here <b>MUST</b> also be listed in $ponyDocsProductsList,'
					. ' usually configured in LocalSettings.php.</i></span>' );
			} elseif ( preg_match( '/(.*)TOC(.*)/', $pieces[2], $matches ) ) {
				$this->data['titletext'] = $matches[1] . ' Table of Contents Page';
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Optionally start this page with {{#manualDescription:Manual Description.}}'
					. ' followed by two line-breaks to set a manual description for the Manual this TOC belongs to.'
					. '</i></span>' );
				$wgOut->addHTML('<br><span class="' . $helpClass . '"><i>'
					. '* If there is no manualDescription tag, please leave one blank line at the top of the page,'
					. ' or else Topic autocreation will fail.'
					. '</i></span>');
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Topics are grouped into sections by section headers.'
					. ' Any line without markup is considered a section header.'
					. ' A section header is required before the the first Topic tag.'
					. ' There cannot be an empty line between a section header and its first Topic.'
					. '</i></span>');
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Topic tags must be part of an unordered list.'
					. ' Use {{#topic:Display Name}} after a * (list item markup) to create Topics.</i></span>' );
			} elseif ( sizeof( $pieces ) >= 2 && PonyDocsProductManual::IsManual( $pieces[1], $pieces[2] ) ) {
				$pManual = PonyDocsProductManual::GetManualByShortName( $pieces[1], $pieces[2] );
				if( $pManual ) {
					$this->data['manualname'] = $pManual->getLongName();
				} else {
					$this->data['manualname'] = $pieces[2];
				}
				$this->data['topicname'] = $pieces[3];
				$this->data['titletext'] = $pieces[2];
			} else {
				$this->data['topicname'] = $pieces[2];
			}
		// Topic page (5 pieces)
		} else {
			$pManual = $ponydocs->getCurrentManual();
			if ( $pManual ) {
				$this->data['manualname'] = $pManual->getLongName();
			} else {
				$this->data['manualname'] = $pieces[2];
			}
			$this->data['topicname'] = $pieces[3];

			$h1 = PonyDocsTopic::FindH1ForTitle( $wgTitle->__toString() );
			if ( $h1 !== FALSE ) {
				$this->data['titletext'] = $h1;
			}
		}

		/**
		 * Get current topic, passing it our global Article object.
		 * From this, generate our TOC based on the current topic selected.
		 * This generates our left sidebar TOC plus our prev/next/start navigation links.
		 * This should ONLY be done if we actually are WITHIN a manual, so special pages like TOC, etc. should not do this!
		 */

		if ( $pManual ) {
			$p = PonyDocsProduct::GetProductByShortName( $this->data['selectedProduct'] );
			$v = PonyDocsProductVersion::GetVersionByName( $this->data['selectedProduct'], $this->data['selectedVersion'] );
			$toc = new PonyDocsTOC( $pManual, $v, $p );
			list( $this->data['manualtoc'], $this->data['tocprev'], $this->data['tocnext'], $this->data['tocstart'] ) =
				$toc->loadContent();
			$this->data['toctitle'] = $toc->getTOCPageTitle();
		}

		/**
		 * Create a PonyDocsTopic from our article. From this we populate:
		 *
		 * topicversions:  List of version names topic is tagged with.
		 * inlinetoc:  Inline TOC shown above article body.
		 * catcode:  Special category code.
		 * cattext:  Category description.
		 * basetopicname:  Base topic name (w/o :<version> at end).
		 * basetopiclink:  Link to special TopicList page to view all same topics.
		 */

        $context = $this->skin->getContext();
 	 	$article = Article::newFromTitle($context->getTitle(), $context);
 	 	$topic = new PonyDocsTopic($article);

		if ( preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/', $wgTitle->__toString() )
			|| preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':.*:.*TOC.*/', $wgTitle->__toString() ) ) {
			$this->data['topicversions'] = PonyDocsWiki::getVersionsForTopic( $topic );
			$this->data['inlinetoc'] = $topic->getSubContents();
			$this->data['versionclasses'] = $topic->getVersionClasses();
			$this->data['versionGroupMessage'] = $this->data['pVersion']->getVersionGroupMessage();

			/**
			 * Sort of a hack -- we only use this right now when loading a TOC page which is new/does not exist.
			 * When this happens a hook (AlternateEdit) adds an inline script to define this JS function,
			 * which populates the edit box with the proper Category tag based on the currently selected version.
			 */

			$this->data['body_onload'] = 'ponyDocsOnLoad();';

			switch( $this->data['catcode'] ) {
				case 0:
					$this->data['cattext'] = 'Applies to latest version which is currently unreleased.';
					break;
				case 1:
					$this->data['cattext'] = 'Applies to latest version.';
					break;
				case 2:
					$this->data['cattext'] = 'Applies to released version(s) but not the latest.';
					break;
				case 3:
					$this->data['cattext'] = 'Applies to latest preview version.';
					break;
				case 4:
					$this->data['cattext'] = 'Applies to one or more preview version(s) only.';
					break;
				case 5:	
					$this->data['cattext'] = 'Applies to one or more unreleased version(s) only.';
					break;
				case -2: /** Means its not a a title name which should be checked. */
					break;
				default:
					$this->data['cattext'] = 'Does not apply to any version of PonyDocs.';
					break;
			}
		}

		$this->data['basetopicname'] = $topic->getBaseTopicName();
		if ( strlen( $this->data['basetopicname'] ) ) {
			$this->data['basetopiclink'] = '<a href="' . $wgScriptPath . '/index.php?title=Special:TopicList&topic='
				. $this->data['basetopicname'] . '">View All</a>';
		}
		$temp = PonyDocsTopic::FindH1ForTitle( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $topic->getTitle()->getText() );
		if ( $temp !== false ) {
			// We got an H1!
			$this->data['pagetitle'] = $temp;
		}
	}

	private function contentActions() {
		global $ponyDocsEmployeeGroup, $wgArticlePath, $wgScriptPath, $wgTitle, $wgUser;

		$groups = $wgUser->getGroups();
		$authProductGroup = PonyDocsExtension::getDerivedGroup();

		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/i', $wgTitle->__toString(), $match ) ) {
			if ( in_array( $ponyDocsEmployeeGroup, $groups ) || in_array( $authProductGroup, $groups ) ) {
				array_pop( $match );  array_shift( $match );
				$title = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . implode( ':', $match );

				$this->data['content_actions']['viewall'] = array(
					'class' => '',
					'text' => 'View All',
					'href' => $wgScriptPath . '/index.php?title=Special:TopicList&topic=' . $title 
				);
			}
			if ( $wgUser->isAllowed( 'branchtopic' ) ) {
				$this->data['content_actions']['branch'] = array(
					'class' => '',
					'text'  => 'Branch',
					'href'	=> $wgScriptPath . '/Special:BranchInherit?titleName=' . $wgTitle->__toString()
				);
			}
		} elseif ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*)TOC(.*)/i', $wgTitle->__toString(), $match ) ) {
			if ( $wgUser->isAllowed( 'branchmanual' ) ) {
				$this->data['content_actions']['branch'] = array(
					'class' => '',
					'text'  => 'Branch',
					'href'	=> $wgScriptPath . '/Special:BranchInherit?toc=' . $wgTitle->__toString()
				);
			}
		}
	}

	/**
	 * Update the nav URLs (toolbox) to include certain special pages for authors and bureaucrats.
	 */
	private function navURLS() {
		global $wgArticlePath, $wgTitle, $wgUser;

		$groups = $wgUser->getGroups();
		$authProductGroup = PonyDocsExtension::getDerivedGroup();

		if ( in_array( 'bureaucrat', $groups ) || in_array( $authProductGroup, $groups ) ) {
			$this->data['nav_urls']['special_doctopics'] = array(
				'href' => str_replace( '$1', 'Special:DocTopics', $wgArticlePath ),
				'text' => 'Document Topics' );

			$this->data['nav_urls']['special_tocmgmt'] = array(
				'href' => str_replace( '$1', 'Special:TOCList', $wgArticlePath ),
				'text' => 'TOC Management' );

			$this->data['nav_urls']['documentation_manuals'] = array(
				'href' => str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':Manuals', $wgArticlePath ),
				'text' => 'Manuals' );
			
			$partialUrl = SpecialDocumentLinks::getDocumentLinksArticle();

			$this->data['nav_urls']['document_links'] = array(
				'href' => str_replace( '$1', 'Special:SpecialDocumentLinks?t=' . $wgTitle->getNsText() . ':'
					. $partialUrl, $wgArticlePath),
				'text' => 'Document Links');
		}
	}

	/**
	 * Output select options respecting a single-level parent/child product hierarchy
	 * 
	 * TODO: Handle multiple levels of parent/child relationships
	 * 
	 * @param string $parent  Short name of parent whose children we want to output
	 */
	private function hierarchicalProductSelect( $parent = NULL ) {
		foreach ( $this->data['products'] as $data ) {
			// We're at the top-level, output all top-level Products
			if ( $parent === NULL && $data['parent'] == '' ) {
				$selected = !strcmp( $data['name'], $this->data['selectedProduct'] ) ? 'selected="selected"' : '';
				echo '<option value="' . $data['name'] . '" ' . $selected . '>';
				echo $data['label'];
				echo "<!-- categories: " . implode(',', $data['categories']) . "-->";
				echo "</option>\n";
				echo $this->hierarchicalProductSelect( $data['name'] );
			} elseif ( $parent !== NULL && $data['parent'] == $parent ) {
				$selected = !strcmp( $data['name'], $this->data['selectedProduct'] ) ? 'selected="selected"' : '';
				echo '<option class="child" value="' . $data['name'] . '" ' . $selected . '>';
				echo '-- ' . $data['label'];
				echo "</option>\n";
			}
		}
	}
}

