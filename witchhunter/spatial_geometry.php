<?php

require "gpointconverter.class.php";

function incrementHash($key, &$hash, $val=1) {
  if (array_key_exists($key, $hash)) {
    $hash[$key] += $val;
  } else {
    $hash[$key] = $val;
  }
}

function addToHash($key, $val, &$hash) {
  if (array_key_exists($key, $hash)) {
    $hash[$key][] = $val;
  } else {
    $hash[$key] = array($val);
  }
}

function matrixIncrement($first, $second, &$hash, $amount=1) {
  if (array_key_exists($first, $hash)) {
    if (isset ($hash[$first][$second])) {
      $hash[$first][$second] += $amount;
    } else {
      $hash[$first][$second] = $amount;
    }
  } else {
    $hash[$first] = array();
    $hash[$first][$second] = $amount;
  }
}

function addToMatrix($first, $second, &$matrix, $value) {
  if (array_key_exists($first, $matrix)) {
    if (isset ($matrix[$first][$second])) {
      $matrix[$first][$second][] = $value;
    } else {
      $matrix[$first][$second] = array($value);
    }
  } else {
    $matrix[$first] = array();
    $matrix[$first][$second] = array($value);
  }
}

function polar2cart($dist, $bearing, $as_deg=false) {
  /* Translate Polar coordinates into Cartesian coordinates
   * based on starting location, distance, and bearing
   * as.deg indicates if the bearing is in degrees (T) or radians (F)
   */
  if ($as_deg) {
    // if bearing is in degrees, convert to radians
    $bearing = $bearing * pi() / 180;
  }
  $x = $dist * sin($bearing);
  $y = $dist * cos($bearing);

  return array("x"=>$x,"y"=>$y);
}

function logPolar2cart($dist, $bearing, $as_deg=false) {
  /* Translate Polar coordinates into Cartesian coordinates
   * based on starting location, distance, and bearing
   * as.deg indicates if the bearing is in degrees (T) or radians (F)
   */

  if ($dist == 0)
    return array("x"=>"0","y"=>"0");

  if (($dist<=1) || (round($dist, 1) == 1.0)) {
    $logDist = log(round($dist, 1) + 1, 10) / 10;
  } else if (($dist > 1) && ($dist < 2)) {
    $logDist = log(round($dist, 1), 10);
  } else {
    $logDist = log($dist, 10);
  }

  if ($as_deg) {
    // if bearing is in degrees, convert to radians
    $bearing = $bearing * pi() / 180;
  }
  $x = $logDist * sin($bearing);
  $y = $logDist * cos($bearing);

  return array("x"=>$x,"y"=>$y);
}

function compassToGeom($compass) {

  if (($compass >= 0) && ($compass <= 90)) {
    $geom = 90 - $compass;
  } else if (($compass > 90) && ($compass <= 270)) {
    $geom = 90 - ($compass - 90) + 270;
  } else if (($compass > 270) && ($compass <= 360)) {
    $geom = 90 - ($compass - 270) + 90;
  }

  return $geom;

}
/*
 *
 * XXX DON'T USE - it has problems somewhere. Use Vincenty instead.
function forwardAzimuth($lon1, $lat1, $lon2, $lat2) {
  $lonDelta = $lon2 - $lon1;
  $y = sin($lonDelta) * cos($lat2);
  $x = (cos($lat1) * sin($lat2)) - (sin($lat1) * cos($lat2) * cos($lonDelta));
  $bearing = rad2deg(atan2($y, $x));
//  return ($bearing + 360) % 360;
  return $bearing;
}
 */
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
/* Vincenty Inverse Solution of Geodesics on the Ellipsoid (c) Chris Veness 2002-2012 */
/*                                                                                    */
/* from: Vincenty inverse formula - T Vincenty, "Direct and Inverse Solutions of      */
/* Geodesics on the Ellipsoid with application of nested equations", Survey Review,   */
/* vol XXII no 176, 1975                                                              */
/*       http://www.ngs.noaa.gov/PUBS_LIB/inverse.pdf                                 */
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */

/**
 * Calculates geodetic distance between two points specified by latitude/longitude using 
 * Vincenty inverse formula for ellipsoids
 *
 * @param   {Number} lat1, lon1: first point in decimal degrees
 * @param   {Number} lat2, lon2: second point in decimal degrees
 * @returns (Number} distance in metres between points
 * PMB modified to return the forward azimuth from point1 to point2
 */
function distVincenty($lon1, $lat1, $lon2, $lat2) {
  $a = 6378137;
  $b = 6356752.314245;
  $f = 1/298.257223563;  // WGS-84 ellipsoid params
  $L = deg2rad($lon2-$lon1);
  $U1 = atan((1-$f) * tan(deg2rad($lat1)));
  $U2 = atan((1-$f) * tan(deg2rad($lat2)));
  $sinU1 = sin($U1);
  $cosU1 = cos($U1);
  $sinU2 = sin($U2);
  $cosU2 = cos($U2);
  
  $lambda = $L;
  $lambdaP = 0;
  $iterLimit = 100;
  while ((abs($lambda-$lambdaP) > .000000000001) && (--$iterLimit>0)) {
    $sinLambda = sin($lambda);
    $cosLambda = cos($lambda);
    $sinSigma = sqrt(($cosU2*$sinLambda) * ($cosU2*$sinLambda) + ($cosU1*$sinU2-$sinU1*$cosU2*$cosLambda) * ($cosU1*$sinU2-$sinU1*$cosU2*$cosLambda));
    if ($sinSigma==0) return 0;  // co-incident points
    $cosSigma = $sinU1*$sinU2 + $cosU1*$cosU2*$cosLambda;
    $sigma = atan2($sinSigma, $cosSigma);
    $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
    $cosSqAlpha = 1 - $sinAlpha*$sinAlpha;
    $cos2SigmaM = $cosSigma - 2*$sinU1*$sinU2/$cosSqAlpha;
    if (is_nan($cos2SigmaM)) $cos2SigmaM = 0;  // equatorial line: cosSqAlpha=0 (¤6)
    $C = $f/16*$cosSqAlpha*(4+$f*(4-3*$cosSqAlpha));
    $lambdaP = $lambda;
    $lambda = $L + (1-$C) * $f * $sinAlpha * ($sigma + $C*$sinSigma*($cos2SigmaM+$C*$cosSigma*(-1+2*$cos2SigmaM*$cos2SigmaM)));
  }

  if ($iterLimit==0) return "error"; // formula failed to converge

  $uSq = $cosSqAlpha * ($a*$a - $b*$b) / ($b*$b);
  $A = 1 + $uSq/16384*(4096+$uSq*(-768+$uSq*(320-175*$uSq)));
  $B = $uSq/1024 * (256+$uSq*(-128+$uSq*(74-47*$uSq)));
  $deltaSigma = $B*$sinSigma*($cos2SigmaM+$B/4*($cosSigma*(-1+2*$cos2SigmaM*$cos2SigmaM)-$B/6*$cos2SigmaM*(-3+4*$sinSigma*$sinSigma)*(-3+4*$cos2SigmaM*$cos2SigmaM)));
  $s = $b*$A*($sigma-$deltaSigma);
  
  $s = number_format($s, 3); // round to 1mm precision
//  return $s;
  
  // note: to return initial/final bearings in addition to distance, use something like:
  $fwdAz = rad2deg(atan2($cosU2*$sinLambda, $cosU1*$sinU2-$sinU1*$cosU2*$cosLambda));
  $revAz = rad2deg(atan2($cosU1*$sinLambda, -$sinU1*$cosU2+$cosU1*$sinU2*$cosLambda));

  if ($fwdAz < 0)
    $fwdAz = $fwdAz + 360;
  if ($revAz < 0)
    $revAz = $revAz + 360;

  //  return { distance: $s, initialBearing: $fwdAz.toDeg(), finalBearing: $revAz.toDeg() };
//  echo "Distance is " . $s . ", initial bearing is " . $fwdAz . ", final bearing is " . $revAz . "\n";

  return $fwdAz;
}

function roundTo($number, $to){ 
  return round($number/$to, 0)* $to; 
}

/**
 * Convex hull calculator
 *
 * convex_hull is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 of the License.
 *
 * convex_hull is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with convex_hull; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Representation of a convex hull, which is calculated based on a given set of 
 * points.
 *
 * The algorithm used to calculate the convex hull is QuickHull.
 * 
 * @author Jakob Westhoff <jakob@php.net>
 * @license GPLv3
 */
class ConvexHull 
{
    /**
     * Set of points provided as input for the calculation.
     * 
     * @var array( array( float, float ) )
     */
    protected $inputPoints;

    /**
     * The points of the convex hull after the quickhull algorithm has been 
     * executed. 
     * 
     * @var array( array( float, float ) )
     */
    protected $hullPoints;


    /**
     * Construct a new ConvexHull object using the given points as input. 
     * 
     * @param array $points 
     */
    public function __construct( array $pofloats ) 
    {
        $this->inputPoints = $pofloats;
        $this->hullPoints = null;
    }

    /**
     * Return the pofloats of the convex hull.
     *
     * The pofloats will be ordered to form a clockwise defined polygon path 
     * around the convex hull. 
     * 
     * @return array( array( float, float ) );
     */
    public function getHullPoints() 
    {
        if ( $this->hullPoints === null ) 
        {
            // Initial run with max and min x value points. 
            // These points are guaranteed to be points of the convex hull
            // Initially the points on both sides of the line are processed.
            $maxX = $this->getMaxXPoint();
            $minX = $this->getMinXPoint();
            $this->hullPoints = array_merge( 
                $this->quickHull( $this->inputPoints, $minX, $maxX ),
                $this->quickHull( $this->inputPoints, $maxX, $minX )
            );
        }

        return $this->hullPoints;
    }

    /**
     * Return the points provided as input point set. 
     * 
     * @return array( array( float, float ) )
     */
    public function getInputPoints() 
    {
        return $this->inputPoints;
    }

    /**
     * Find and return the point with the maximal X value. 
     * 
     * @return array( float, float )
     */
    protected function getMaxXPoint() 
    {
        $max = $this->inputPoints[0];
        foreach( $this->inputPoints as $p ) 
        {
            if ( $p[0] > $max[0] ) 
            {
                $max = $p;
            }
        }
        return $max;
    }

    /**
     * Find and return the point with the minimal X value. 
     * 
     * @return array( float, float )
     */
    protected function getMinXPoint() 
    {
        $min = $this->inputPoints[0];
        foreach( $this->inputPoints as $p ) 
        {
            if ( $p[0] < $min[0] ) 
            {
                $min = $p;
            }
        }
        return $min;
    }

    /**
     * Calculate a distance indicator between the line defined by $start and 
     * $end and an arbitrary $point.
     *
     * The value returned is not the correct distance value, but is sufficient 
     * to determine the point with the maximal distance from the line. The 
     * returned distance indicator is therefore directly relative to the real 
     * distance of the point. 
     *
     * The returned distance value may be positive or negative. Positive values 
     * indicate the point is left of the specified vector, negative values 
     * indicate it is right of it. Furthermore if the value is zero the point 
     * is colinear to the line.
     * 
     * @param float $start 
     * @param float $end 
     * @param float $point 
     * @return float
     */
    protected function calculateDistanceIndicator( array $start, array $end, array $point ) 
    {
        /*
         * The real distance value could be calculated as follows:
         * 
         * Calculate the 2D Pseudo crossproduct of the line vector ($start 
         * to $end) and the $start to $point vector. 
         * ((y2*x1) - (x2*y1))
         * The result of this is the area of the parallelogram created by the 
         * two given vectors. The Area formula can be written as follows:
         * A = |$start->$end| * h
         * Therefore the distance or height is the Area divided by the length 
         * of the first vector. This division is not done here for performance 
         * reasons. The length of the line does not change for each of the 
         * comparison cycles, therefore the resulting value can be used to 
         * finde the point with the maximal distance without performing the 
         * division.
         *
         * Because the result is not returned as an absolute value its 
         * algebraic sign indicates of the point is right or left of the given 
         * line.
         */

        $vLine = array( 
            $end[0] - $start[0],
            $end[1] - $start[1]
        );

        $vPoint = array( 
            $point[0] - $start[0],
            $point[1] - $start[1]
        );

        return ( ( $vPoint[1] * $vLine[0] ) - ( $vPoint[0] * $vLine[1] ) );
    }

    /**
     * Calculate the distance indicator for each given point and return an 
     * array containing the point and the distance indicator. 
     *
     * Only points left of the line will be returned. Every point right of the 
     * line or colinear to the line will be deleted.
     * 
     * @param array $start 
     * @param array $end 
     * @param array $points 
     * @return array( array( point, distance ) )
     */
    protected function getPointDistanceIndicators( array $start, array $end, array $points ) 
    {
        $resultSet = array();

        foreach( $points as $p ) 
        {
            if ( ( $distance = $this->calculateDistanceIndicator( $start, $end, $p ) ) > 0 ) 
            {
                $resultSet[] = array( 
                    'point'    => $p,
                    'distance' => $distance
                );
            }
            else 
            {
                continue;
            }
        }

        return $resultSet;
    }

    /**
     * Get the point which has the maximum distance from a given line.
     *
     * @param array $pointDistanceSet 
     * @return array( float, float )
     */
    protected function getPointWithMaximumDistanceFromLine( array $pointDistanceSet ) 
    {
        $maxDistance = 0;
        $maxPoint    = null;

        foreach( $pointDistanceSet as $p ) 
        {
            if ( $p['distance'] > $maxDistance )
            {
                $maxDistance = $p['distance'];
                $maxPoint    = $p['point'];
            }
        }

        return $maxPoint;
    }

    /**
     * Extract the points from a point distance set. 
     * 
     * @param array $pointDistanceSet 
     * @return array
     */
    protected function getPointsFromPointDistanceSet( $pointDistanceSet ) 
    {
        $points = array();

        foreach( $pointDistanceSet as $p ) 
        {
            $points[] = $p['point'];
        }

        return $points;
    }

    /**
     * Execute a QuickHull run on the given set of points, using the provided 
     * line as delimiter of the search space.
     *
     * Only points left of the given line will be analyzed. 
     * 
     * @param array $points 
     * @param array $start 
     * @param array $end 
     * @return array
     */
    protected function quickHull( array $points, array $start, array $end ) 
    {
        $pointsLeftOfLine = $this->getPointDistanceIndicators( $start, $end, $points );
        $newMaximalPoint = $this->getPointWithMaximumDistanceFromLine( $pointsLeftOfLine );
        
        if ( $newMaximalPoint === null ) 
        {
            // The current delimiter line is the only one left and therefore a 
            // segment of the convex hull. Only the end of the line is returned 
            // to not have points multiple times in the result set.
            return array( $end );
        }

        // The new maximal point creates a triangle together with $start and 
        // $end, Everything inside this trianlge can be ignored. Everything 
        // else needs to handled recursively. Because the quickHull invocation 
        // only handles points left of the line we can simply call it for the 
        // different line segements to process the right kind of points.
        $newPoints = $this->getPointsFromPointDistanceSet( $pointsLeftOfLine );
        return array_merge(
            $this->quickHull( $newPoints, $start, $newMaximalPoint ),
            $this->quickHull( $newPoints, $newMaximalPoint, $end )
        );
    }
}

function centroid($polygon) {

  $a = area($polygon);

  $n = count($polygon);
  $polygon[$n] = $polygon[0];

  $cx = 0;
  $cy = 0;

  for ($i=0;$i<$n;$i++) {
    $cx += ($polygon[$i][0] + $polygon[$i+1][0]) * ( ($polygon[$i][0]*$polygon[$i+1][1]) - ($polygon[$i+1][0]*$polygon[$i][1]) );
    $cy += ($polygon[$i][1] + $polygon[$i+1][1]) * ( ($polygon[$i][0]*$polygon[$i+1][1]) - ($polygon[$i+1][0]*$polygon[$i][1]) );
  }
  
  return(array( (1/(6*$a))*$cx,(1/(6*$a))*$cy));
  
};

function area($polygon) {
  $n = count($polygon);
  $polygon[$n] = $polygon[0];
  $area = 0;
  for ($i=0;$i<$n;$i++) {
    $j = ($i + 1);
    $area = $area + (($polygon[$i][0] * $polygon[$j][1]) - ($polygon[$i][1] * $polygon[$j][0]));
  }
  $area = $area / 2;
  return($area);
}

function areaInSqKm($in_polygon) {

  $geoConverter = new GPointConverter();

//  $newXs = array();
//  $newYs = array();

//  $xmin = 0;
//  $ymin = 0;

  foreach ($in_polygon as $point) {
    /* Points are returned in the format (UTM easting, UTM northing, UTM zone) */
    /* i.e., x, y, zone */
    $UTFpoint = $geoConverter->convertLatLngToUtm($point[1], $point[0]);
//    $newXs[] = $UTFpoint[0];
//    $newYs[] = $UTFpoint[1];
      $UTFpoint[0] = $UTFpoint[0] / 1000;
      $UTFpoint[1] = $UTFpoint[1] / 1000;
    $polygon[] = $UTFpoint;
  }
/*
  $xmin = min($newXs);
  $ymin = min($newYs);
 */
  $n = count($polygon);
  $polygon[$n] = $polygon[0];
  $area = 0;
  for ($i=0;$i<$n;$i++) {
    $j = ($i + 1);

    $point1 = $polygon[$i];
    $point2 = $polygon[$j];
/*
    $point1[0] = $point1[0] - $xmin;
    $point1[1] = $point1[1] - $ymin;
    $point2[0] = $point2[0] - $xmin;
    $point2[1] = $point2[1] - $ymin;
 */
//    echo "LON/LAT: " . $in_polygon[$i][0] . ", " . $in_polygon[$i][1] . "; UTM east/north: " . $point1[0] . ", " . $point1[1] . "\n";
//    echo "LON/LAT: " . $in_polygon[$j][0] . ", " . $in_polygon[$j][1] . "; UTM east/north: " . $point2[0] . ", " . $point2[1] . "\n";

    $area = $area + (($point2[0] + $point1[0]) * ($point2[1] - $point1[1]));
//    echo "area so far is " . $area . "\n";
//    $area = $area + (($point1[0] * $point2[1]) - ($point1[1] * $point2[0]));
//    $area = $area + (($polygon[$i][0] * $polygon[$j][1]) - ($polygon[$i][1] * $polygon[$j][0]));
  }
  $area = $area / 2;
  return(abs($area));
}

function polyCenter($polygon) {

  $p = $polygon;

  $x=0;
  $y=0;

  $n = count($p);

  for($i=0;$i<$n;$i++) {
    $x+=$p[$i][0];
    $y+=$p[$i][1];
  }

  ## OUT: X,Y coordinates of approx center in array ##
  return array($x/$n,$y/$n);
}

function pointInPolygon($point, $Polygon) {

  $vertices = array();
  $inside = false;

  foreach($Polygon as $vertex) {
    if (($vertex[0] == $point[0]) && ($vertex[1] == $point[1])) {
      return true; // The point matches a vertex
    }
    $vertices[] = $vertex;
  }
  $vertices[] = $Polygon[0];
  $vertices_count = count($vertices);

  for ($i=1; $i < $vertices_count; $i++) {
    $vertex1 = $vertices[$i-1];
    $vertex2 = $vertices[$i];

    if ($point[1] > min($vertex1[1], $vertex2[1])) {
      if ($point[1] <= max($vertex1[1], $vertex2[1])) {
        if ($point[0] <= max($vertex1[0], $vertex2[0])) {
          if ($vertex1[0] != $vertex2[1]) {
            $xinters = ($point[1] - $vertex1[1])*($vertex2[0]-$vertex1[0]) / ($vertex2[1] - $vertex1[1]) + $vertex1[0];
          }
          if (($vertex1[0] == $vertex2[0]) || ($point[0] <= $xinters)) {
            $inside = !($inside);
          }
        }
      }
    }
  }
  return $inside;
}

function haversineDistance($point1, $point2) {

  $lng = $point1[0];
  $lat = $point1[1];
  $lng2 = $point2[0];
  $lat2 = $point2[1];

  $radius = 6378100; // radius of earth in meters
  $latDist = $lat - $lat2;
  $lngDist = $lng - $lng2;
  $latDistRad = deg2rad($latDist);
  $lngDistRad = deg2rad($lngDist);
  $sinLatD = sin($latDistRad/2);
  $sinLngD = sin($lngDistRad/2);
  $cosLat1 = cos(deg2rad($lat));
  $cosLat2 = cos(deg2rad($lat2));
  $a = $sinLatD*$sinLatD + $cosLat1*$cosLat2*$sinLngD*$sinLngD;
  if($a<0) $a = -1*$a;
  $c = 2*atan2(sqrt($a), sqrt(1-$a));
  $distance = $radius*$c;

  return $distance;
}

/* Other stats functions */

function average($arr)
{
    if (!count($arr)) return 0;

    $sum = 0;
    for ($i = 0; $i < count($arr); $i++)
    {
        $sum += $arr[$i];
    }

    return $sum / count($arr);
}

function variance($arr)
{
    if (!count($arr)) return 0;

    $mean = average($arr);

    $sos = 0;    // Sum of squares
    for ($i = 0; $i < count($arr); $i++)
    {
        $sos += ($arr[$i] - $mean) * ($arr[$i] - $mean);
    }

    return $sos / (count($arr)-1);  // denominator = n-1; i.e. estimating based on sample 
                                    // n-1 is also what MS Excel takes by default in the
                                    // VAR function
}

function stdev($arr) {

    if (!count($arr)) return 0;

    $variance = variance($arr);

    $stdev = sqrt($variance);

    return $stdev;
}

?>
