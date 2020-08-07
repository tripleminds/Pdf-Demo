<?php
require('fpdf/fpdf.php');
define('EURO', chr(128) );
define('EURO_VAL', 6.55957 );
//session_start();
//$FsNoCourses = array();
//$SsNoCourses = array();


// Stream handler to read from global variables
class VariableStream
{
	private $varname;
	private $position;

	function stream_open($path, $mode, $options, &$opened_path)
	{
		$url = parse_url($path);
		$this->varname = $url['host'];
		if(!isset($GLOBALS[$this->varname]))
		{
			trigger_error('Global variable '.$this->varname.' does not exist', E_USER_WARNING);
			return false;
		}
		$this->position = 0;
		return true;
	}

	function stream_read($count)
	{
		$ret = substr($GLOBALS[$this->varname], $this->position, $count);
		$this->position += strlen($ret);
		return $ret;
	}

	function stream_eof()
	{
		return $this->position >= strlen($GLOBALS[$this->varname]);
	}

	function stream_tell()
	{
		return $this->position;
	}

	function stream_seek($offset, $whence)
	{
		if($whence==SEEK_SET)
		{
			$this->position = $offset;
			return true;
		}
		return false;
	}
	
	function stream_stat()
	{
		return array();
	}
}


class document extends FPDF
{
	////image begin
	
	function __construct($orientation='P', $unit='mm', $format='A4')
	{
		parent::__construct($orientation, $unit, $format);
		// Register var stream protocol
		stream_wrapper_register('var', 'VariableStream');
	}

	function MemImage($data, $x=null, $y=null, $w=0, $h=0, $link='')
	{
		// Display the image contained in $data
		$v = 'img'.md5($data);
		$GLOBALS[$v] = $data;
		$a = getimagesize('var://'.$v);
		if(!$a)
			$this->Error('Invalid image data');
		$type = substr(strstr($a['mime'],'/'),1);
		$this->Image('var://'.$v, $x, $y, $w, $h, $type, $link);
		unset($GLOBALS[$v]);
	}

	function GDImage($im, $x=null, $y=null, $w=0, $h=0, $link='')
	{
		// Display the GD image associated with $im
		ob_start();
		imagepng($im);
		$data = ob_get_clean();
		$this->MemImage($data, $x, $y, $w, $h, $link);
	}
	
	////image end
	

// private variables
var $colonnes;
var $format;
var $angle=0;
var $errorFlag;

// private functions
function RoundedRect($x, $y, $w, $h, $r, $style = '')
{
	$k = $this->k;
	$hp = $this->h;
	if($style=='F')
		$op='f';
	elseif($style=='FD' || $style=='DF')
		$op='B';
	else
		$op='S';
	$MyArc = 4/3 * (sqrt(2) - 1);
	$this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
	$xc = $x+$w-$r ;
	$yc = $y+$r;
	$this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

	$this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
	$xc = $x+$w-$r ;
	$yc = $y+$h-$r;
	$this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
	$this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
	$xc = $x+$r ;
	$yc = $y+$h-$r;
	$this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
	$this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
	$xc = $x+$r ;
	$yc = $y+$r;
	$this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
	$this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
	$this->_out($op);
}

function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
{
	$h = $this->h;
	$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
						$x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
}

function Rotate($angle, $x=-1, $y=-1)
{
	if($x==-1)
		$x=$this->x;
	if($y==-1)
		$y=$this->y;
	if($this->angle!=0)
		$this->_out('Q');
	$this->angle=$angle;
	if($angle!=0)
	{
		$angle*=M_PI/180;
		$c=cos($angle);
		$s=sin($angle);
		$cx=$x*$this->k;
		$cy=($this->h-$y)*$this->k;
		$this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
	}
}

function _endpage()
{
	if($this->angle!=0)
	{
		$this->angle=0;
		$this->_out('Q');
	}
	parent::_endpage();
}

// public functions
function sizeOfText( $texte, $largeur )
{
	$index    = 0;
	$nb_lines = 0;
	$loop     = TRUE;
	while ( $loop )
	{
		$pos = strpos($texte, "\n");
		if (!$pos)
		{
			$loop  = FALSE;
			$ligne = $texte;
		}
		else
		{
			$ligne  = substr( $texte, $index, $pos);
			$texte = substr( $texte, $pos+1 );
		}
		$length = floor( $this->GetStringWidth( $ligne ) );
		$res = 1 + floor( $length / $largeur) ;
		$nb_lines += $res;
	}
	return $nb_lines;
}

// Rotate Text
function RotatedText($x, $y, $txt, $angle)
{
	//Text rotated around its origin
	$this->Rotate($angle,$x,$y);
	$this->Text($x,$y,$txt);
	$this->Rotate(0);
}

function Matric($MatriculationNumber)
{
	$this->SetFont("Arial", "", 10 );
	$this->SetXY(154.5, 91);
	$this->Cell(0,0, " ". $MatriculationNumber,'0','L','L');
}


// Our defined function is here
function draft($MatriculationNumber, $name, $course, $date){

	$this->SetFont("times", "B", 12);
	$this->SetXY(150,$yAddressLine+25);
		// $this->WordWrap($paragraph2,1000);
		// $this->Write(6,$paragraph2);
		$this->MultiCell(0,5.5,$date,0,L);
		$yAddressLine = $this->GetY();


$letterTitle = 'OFFER OF PROVISIONAL ADMISSION INTO LAGOS STATE UNIVERISTY' ;
$this->SetFont( "Arial", "BU", 14);
$this->SetXY(10,$yAddressLine+11);
//$this->WordWrap($letterTitle,1000);
$this->Write(7,$letterTitle);
$yAddressLine = $this->GetY();


	$paragraph1 = "Dear ". $name . ',' ;
	$this->SetFont("times", "", 12);
	$this->SetXY(10,$yAddressLine+11);
		// $this->WordWrap($paragraph2,1000);
		// $this->Write(6,$paragraph2);
		$this->MultiCell(0,5.5,$paragraph1,0,J);
		$yAddressLine = $this->GetY();


	$paragraph2 = "You have been offered provisional admission into Lagos State Univeristy to study " . $course .   " " . "for a period of five years.";
	$this->SetFont("times", "", 12);
	$this->SetXY(10,$yAddressLine+8);
		// $this->WordWrap($paragraph2,1000);
		// $this->Write(6,$paragraph2);
	$this->MultiCell(0,5.5,$paragraph2,0,J);
	$yAddressLine = $this->GetY();

	
	$paragraph3 = "You are however expected to make a  payment of 30,000 as acceptance fees in order to for this offer to be valid ";
	$this->SetFont("times", "", 12);
	$this->SetXY(10,$yAddressLine+8);
		// $this->WordWrap($paragraph2,1000);
		// $this->Write(6,$paragraph2);
	$this->MultiCell(0,5.5,$paragraph3,0,J);
	$yAddressLine = $this->GetY();


	$paragraph4 = "Accept my hearty congratulations, please ";
	$this->SetFont("times", "", 12);
	$this->SetXY(10,$yAddressLine+8);
		// $this->WordWrap($paragraph2,1000);
		// $this->Write(6,$paragraph2);
	$this->MultiCell(0,5.5,$paragraph4,0,J);
	$yAddressLine = $this->GetY();


	$paragraph5 = "For: Director, ODLRI ";
	$this->SetFont("times", "B", 12);
	$this->SetXY(10,$yAddressLine+27);
		// $this->WordWrap($paragraph2,1000);
		// $this->Write(6,$paragraph2);
	$this->MultiCell(0,5.5,$paragraph5,0,J);
	$yAddressLine = $this->GetY();





}







function imageLoader(){
	//$this->RoundedRect(30+ 270 - 45, 7 , 45, 24, 2, 'D');
	
}




// Page footer
/*function Footer()
{
    $this->SetY(-33.5);
	$this->SetX(-85);
    // Arial italic 8
	$this->SetTextColor(5,169,63);
    $this->SetFont('Arial','BI',8);
    // Page number
    $this->Cell(0,7,'Screening Result Generated on '.date('d-m-Y h:i:s A'),0,'R');
}*/



//PDs only


}
?>
