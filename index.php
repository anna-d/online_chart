<?php
	include("../classes/Translation.php");
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
       "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title>OpenSeaMap - <?php echo $t->tr("dieFreieSeekarte")?></title>
		<meta name="AUTHOR" content="Olaf Hannemann" />
		<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
		<meta http-equiv="content-language" content="<?= $t->getCurrentLanguage() ?>" />
		<link rel="SHORTCUT ICON" href="../resources/icons/OpenSeaMapLogo_16.png"/>
		<link rel="stylesheet" type="text/css" href="map-full.css">
		<script type="text/javascript" src="./javascript/openlayers/OpenLayers.js"></script>
		<script type="text/javascript" src="./javascript/OpenStreetMap.js"></script>
		<script type="text/javascript" src="./javascript/utilities.js"></script>
		<script type="text/javascript" src="./javascript/map_utils.js"></script>
		<script type="text/javascript" src="./javascript/harbours.js"></script>
		<script type="text/javascript">

			var map;
			var arrayMaps = new Array();

			// Position and zoomlevel of the map  (will be overriden with permalink parameters or cookies)
			var lon = 11.6540;
			var lat = 54.1530;
			var zoom = 10;

			// Work around for accessing translations from harbour.js
			var linkText = "<?=$t->tr('descrSkipperGuide')?>";

			var layer_download

			// Load map for the first time
			function init() {
				var buffZoom = parseInt(getCookie("zoom"));
				var buffLat = parseFloat(getCookie("lat"));
				var buffLon = parseFloat(getCookie("lon"));
				if (buffZoom != -1) {
					zoom = buffZoom;
				}
				if (buffLat != -1 && buffLon != -1) {
					lat = buffLat;
					lon = buffLon;
				}
				drawmap();
			}

			// Set current language for internationalization
			OpenLayers.Lang.setCode("<?= $t->getCurrentLanguage() ?>");

			// Show popup window with the map key
			function showMapKey() {
				legendWindow = window.open("legend.php?lang=<?= $t->getCurrentLanguage() ?>", "MapKey", "width=880, height=680, status=no, scrollbars=yes, resizable=yes");
 				legendWindow.focus();
			}

			// Show Download section
			function showMapDownload() {
				//alert("Download");
				layer_download.setVisibility(true);
				document.getElementById("downloadmenu").style.visibility = 'visible';
			}

			function closeMapDownload() {
				layer_download.setVisibility(false);
				document.getElementById("downloadmenu").style.visibility = 'hidden';
			}

			function downloadMap() {
				var name = document.getElementById('info_dialog').innerHTML;
				var format = _buoy_shape = document.getElementById("mapFormat").value;
				if (format == "unknown") {
					alert("Bitte wählen sie ein Format.");
					return;
				}
				var url = "http://sourceforge.net/projects/openseamap/files/Maps/Europe/Baltic%20Sea/Harbour/"+ name + "/OSeaM-" + name + "." + format + "/download";
				
				downloadWindow = window.open(url);
				//http://sourceforge.net/projects/openseamap/files/Maps/Europe/Baltic%20Sea/Harbour/StralsundHaven/OSeaM-StralsundHaven.WCI/download
			}

			function selectedMap (evt) {
				var selectedMap = evt.feature.id.split(".");
				var mapName = arrayMaps[selectedMap[2].split("_")[1]];
				//alert(mapName);
				document.getElementById('info_dialog').innerHTML=""+ mapName +"";
			}

			function drawmap() {
				map = new OpenLayers.Map('map', {
					projection: projMerc,
					displayProjection: proj4326,
					eventListeners: {
						"moveend": mapEventMove,
						"zoomend": mapEventZoom
					},
					controls: [
						new OpenLayers.Control.Permalink(),
						new OpenLayers.Control.Navigation(),
						new OpenLayers.Control.ScaleLine({topOutUnits : "nmi", bottomOutUnits: "km", topInUnits: 'nmi', bottomInUnits: 'km', maxWidth: '40'}),
						new OpenLayers.Control.LayerSwitcher(),
						new OpenLayers.Control.MousePosition(),
						new OpenLayers.Control.OverviewMap(),
						new OpenLayers.Control.PanZoomBar()],
						maxExtent:
						new OpenLayers.Bounds(-20037508.34, -20037508.34, 20037508.34, 20037508.34),
					numZoomLevels: 18,
					maxResolution: 156543,
					units: 'meters'
				});

				// Add Layers to map-------------------------------------------------------------------------------------------------------
				// Mapnik
				var layer_mapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
				// Osmarender
				var layer_tah = new OpenLayers.Layer.OSM.Osmarender("Osmarender");
				// Seamark
				var layer_seamark = new OpenLayers.Layer.TMS("<?=$t->tr("Seezeichen")?>", "http://tiles.openseamap.org/seamark/",
				{ numZoomLevels: 18, type: 'png', getURL:getTileURL, isBaseLayer:false, displayOutsideMaxExtent:true});
				// Sport
				var layer_sport = new OpenLayers.Layer.TMS("Sport", "http://tiles.openseamap.org/sport/",
				{ numZoomLevels: 18, type: 'png', getURL:getTileURL, isBaseLayer:false, visibility: false, displayOutsideMaxExtent:true});
				// Harbours
				layer_harbours = new OpenLayers.Layer.Markers("<?=$t->tr("harbours")?>",
				{ projection: new OpenLayers.Projection("EPSG:4326"), visibility: true, displayOutsideMaxExtent:true});
				layer_harbours.setOpacity(0.8);
				// Map download
				layer_download = new OpenLayers.Layer.Vector("Map Download", {visibility: false});

				addDownloadlayer();

				map.addLayers([layer_mapnik, layer_tah, layer_seamark, layer_harbours, layer_download, layer_sport]);

				if (!map.getCenter()) {
					jumpTo(lon, lat, zoom);
				}
				var selectDownload = new OpenLayers.Control.SelectFeature(layer_download);
				map.addControl(selectDownload);
				selectDownload.activate();
				layer_download.events.register("featureselected", layer_download, selectedMap);
			}

			// Map event listener moved
			function mapEventMove(event) {
				// Set cookie for remembering lat lon values
				setCookie("lat", y2lat(map.getCenter().lat).toFixed(5));
				setCookie("lon", x2lon(map.getCenter().lon).toFixed(5));
				// Update harbour layer
				refresh_oseamh();
			}

			// Map event listener Zoomed
			function mapEventZoom(event) {
				// Set cookie for remembering zoomlevel
				setCookie("zoom", map.getZoom());
			}

			function addDownloadlayer() {

				var xmlDoc=loadXMLDoc("./gml/map_download.xml");
				try {
					var root = xmlDoc.getElementsByTagName("maps")[0];
					var items = root.getElementsByTagName("map");
				} catch(e) {
					alert("Error (root): "+ e);
					return -1;
				}
				for (var i=0; i < items.length; ++i) {
					//alert(i);
					var item = items[i];
					try {
						var n = item.getElementsByTagName("north")[0].childNodes[0].nodeValue;
						var s = item.getElementsByTagName("south")[0].childNodes[0].nodeValue;
						var e = item.getElementsByTagName("east")[0].childNodes[0].nodeValue;
						var w = item.getElementsByTagName("west")[0].childNodes[0].nodeValue;
					} catch(e) {
						alert("Error (load): "+ e);
						return -1;
					}
					var bounds = new OpenLayers.Bounds(w, s, e, n);
					bounds.transform(new OpenLayers.Projection("EPSG:4326"), new
					OpenLayers.Projection("EPSG:900913"));
					var box = new OpenLayers.Feature.Vector(bounds.toGeometry());
					layer_download.addFeatures(box);
					arrayMaps[box.id.split("_")[1]] = item.getElementsByTagName("name")[0].childNodes[0].nodeValue;
				}
			}

		</script>
	</head>
	<body onload=init();>
		<div id="map" style="position:absolute; bottom:0px; left:0px;"></div>
		<div id="layerswitcher"></div>
		<div style="position:absolute; bottom:48px; left:12px; width:700px;">
			<img src="../resources/icons/somerights20.png" height="30px" title="<?=$t->tr("SomeRights")?>" onClick="window.open('http://creativecommons.org/licenses/by-sa/2.0')" />
		</div>
		<div id="topmenu" style="position:absolute; top:10px; left:60px;">
			<ul>
				<li onClick="window.location.href='http://openseamap.org/'"><IMG src="../resources/icons/OpenSeaMapLogo_88.png" width="24" height="24" align="center" border="0"><?=$t->tr("Startseite")?></img></li>
				<li>&nbsp;|&nbsp;</li>
				<li onClick="window.location.href='./map_edit.php'"><IMG src="./resources/action/edit.png" width="24" height="24" align="center" border="0"><?=$t->tr("edit")?></img></li>
				<li>&nbsp;|&nbsp;</li>
				<li onClick="showMapKey()"><IMG src="./resources/action/info.png" width="24" height="24" align="center" border="0"><?=$t->tr("Legende")?></img></li>
				<li>&nbsp;|&nbsp;</li>
				<li onClick="showMapDownload()"><IMG src="./resources/action/download.png" width="24" height="24" align="center" border="0">Karte Herunterladen</img></li>
			</ul>
		</div>
		<div id="downloadmenu" style="position:absolute; top:50px; left:60px; visibility:hidden;">
			<b>Karte Herunterladen</b><br/><br/>
			<table border="0" width="100%">
				<tr>
					<td>
						Name:
					</td>
					<td>
						<div id="info_dialog">&nbsp;Bitte Wählen<br/></div>
					</td>
				</tr>
				<tr>
					<td>
						Format:
					<td>
						<select id="mapFormat">
							<option value="unknown"/><?=$t->tr("unknown")?>
							<option value="png"/>png
							<option value="kap"/>kap
							<option value="WCI"/>WCI
							<option value="kmz"/>kmz
							<option value="jpr"/>jpr
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<br/>
						<input type="button" id="buttonMapDownload" value="Herunterladen" onclick="downloadMap()">
					</td>
					<td align="right">
						<br/>
						<input type="button" id="buttonMapClose" value="Schließen" onclick="closeMapDownload()">
					</td>
				</tr>
			</table>
		</div>
	</body>
</html>
