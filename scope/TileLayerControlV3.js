/*

TileLayerControl_v3.js

Copyright 2010 - Marcelo Montagna  - http://maps.forum.nu

Free to use as long as copyright notices are left unchanged.
Please save this file to your own server. Do not link directly,
or unexpected things might happen :-)

*/

TileLayerControl = function(MOptions) {
	MOptions = MOptions ? MOptions : {};
	this.parent = MOptions.parent ? MOptions.parent : null;
	this.overlay = MOptions.overlay ? MOptions.overlay : null;
	this.caption = MOptions.caption ? MOptions.caption : 'Overlay';
	this.self = this;

	this.background = '#eeeeee';

	this.knobWidth = MOptions.knobWidth ? MOptions.knobWidth : 20;
	this.value = MOptions.value ? MOptions.value : 50;
	this.ondrag = MOptions.ondrag ? MOptions.ondrag : null;
	this.ondragend = MOptions.ondragend ? MOptions.ondragend : null;
	this.dragStartX = null;
	this.initialize();
	this.setSlider();
};

TileLayerControl.prototype.initialize = function() {
	var self = this.self;

	this.container = document.createElement('DIV');
	this.container.style.position = 'relative';
	this.container.style.overflow = 'hidden';
	this.container.style.textAlign = 'left';
//	this.container.style.margin = '10px';
//	this.container.style.background = this.background;

	if (this.parent) {
		this.parent.appendChild(this.container);
	}



	var oTable = document.createElement('TABLE');
	this.container.appendChild(oTable);
	oTable.setAttribute('width','100%');
	oTable.setAttribute('cellpadding','2');
	oTable.setAttribute('cellspacing','0');
	oTable.style.background = this.background;

	var oTBody = document.createElement('TBODY');
	oTable.appendChild(oTBody);

// ----------------
	var oTRow = document.createElement('TR');
	oTBody.appendChild(oTRow);

	var oTCell = document.createElement('TD');
	oTCell.style.textAlign = 'center';
	oTCell.style.width = '10px';
	oTCell.style.verticalAlign = 'middle';
	oTRow.appendChild(oTCell);
	oTCell.style.font = 'normal 10px verdana';

	if (document.all && !window.opera && document.createElement) {
		this.cBox = document.createElement('<INPUT type="checkbox">');
	}
	else {
		this.cBox = document.createElement('INPUT');
		this.cBox.setAttribute('type','checkbox');
	}
	this.cBox.onclick = function(){self.cbClick()};

	oTCell.appendChild(this.cBox);

//---
	var oTCell = document.createElement('TD');
	oTCell.style.textAlign = 'left';
	oTCell.style.verticalAlign = 'middle';
	oTRow.appendChild(oTCell);
	oTCell.style.font = 'normal 10px verdana';
	oTCell.style.paddingRight = '20px';
	oTCell.style.width = '100px';
	oTCell.innerHTML = this.caption;



// ==========================================================


	var oTCell = document.createElement('TD');
	oTCell.style.textAlign = 'left';
	oTCell.style.verticalAlign = 'middle';
	oTRow.appendChild(oTCell);
	oTCell.style.font = 'normal 10px verdana';


	var oTable = document.createElement('TABLE');
	oTCell.appendChild(oTable);
//	oTable.setAttribute('width','100%');
	oTable.setAttribute('cellpadding','0');
	oTable.setAttribute('cellspacing','0');
	oTable.style.background = this.background;
	this.sliderTable = oTable;

	var oTBody = document.createElement('TBODY');
	oTable.appendChild(oTBody);

// ----------------
	var oTRow = document.createElement('TR');
	oTBody.appendChild(oTRow);

//---
	var oTCell = document.createElement('TD');
	oTCell.style.textAlign = 'right';
	oTCell.style.verticalAlign = 'middle';
	oTRow.appendChild(oTCell);
	oTCell.style.font = 'normal 10px verdana';
	oTCell.innerHTML = 'Opacity: ';

//---
	var oTCell = document.createElement('TD');
	oTCell.style.textAlign = 'left';
	oTCell.style.verticalAlign = 'middle';
	oTRow.appendChild(oTCell);
	oTCell.style.font = 'normal 10px verdana';

	this.slider = this.createSlider();
	oTCell.appendChild(this.slider);

//---
	var oTCell = document.createElement('TD');
	oTCell.style.textAlign = 'left';
	oTCell.style.verticalAlign = 'middle';
	oTRow.appendChild(oTCell);
	oTCell.style.font = 'normal 10px verdana';

	this.label = this.createLabel();
	oTCell.appendChild(this.label);
// ==========================================================

	this.totalPixels = this.slider.clientWidth + 2;

	this.attachEventHandlers();
	this.sliderTable.style.display = 'none';

};


TileLayerControl.prototype.cbClick = function() {
	if (this.cBox.checked) {
		this.overlay.show();
		this.sliderTable.style.display = '';
		if (this.overlay) {
			this.overlay.fadeToOpacity(this.value);
		}

	}
	else {
		if (this.overlay) {
			this.overlay.fadeToOpacity(0);
		}
//		this.overlay.hide();
		this.sliderTable.style.display = 'none';
	}
}

TileLayerControl.prototype.createLabel = function() {
	var label = document.createElement('DIV');
	label.style.position = 'relative';
	label.style.overflow = 'hidden';
	label.style.textAlign = 'center';
	label.style.height = '16px';
	label.style.width = '70px';
	label.style.font = 'bold 12px verdana';
	return label;
};

TileLayerControl.prototype.createSlider = function() {
	var slider = document.createElement('DIV');
	slider.style.position = 'relative';
	slider.style.overflow = 'hidden';
	slider.style.border = '1px solid gray';
	slider.style.textAlign = 'left';
	slider.style.height = '16px';
	slider.style.width = '200px';
	slider.style.cursor = 'pointer';

	slider.style.backgroundImage = "url('knob.png')";
	slider.style.backgroundRepeat = "no-repeat";
	slider.style.backgroundPositionX  = '50px';

//----------
	this.sliderKnob = document.createElement('DIV');
	this.sliderKnob.style.position = 'absolute';
	this.sliderKnob.style.width = this.knobWidth + 'px';
	this.sliderKnob.style.height = this.knobWidth + 'px';
	this.sliderKnob.style.background = '#888888';
	this.sliderKnob.title = 'Drag and release to change opacity ...';
//	slider.appendChild(this.sliderKnob);


//----------

//	this.sliderKnob = new G.DraggableObject(this.sliderKnob,{container:slider,left:0,top:0});
	return slider;

};





TileLayerControl.prototype.attachEventHandlers = function() {
	var self = this.self;
	google.maps.event.addDomListener(this.slider,'mousedown',function(ev){self.sliderMouseDown(ev,this)});
	google.maps.event.addDomListener(this.slider,'mouseup',function(ev){self.sliderMouseUp(ev,this)});
	google.maps.event.addDomListener(this.slider,'mousemove',function(ev){self.sliderMouseMove(ev,this)});

};

TileLayerControl.prototype.sliderMouseDown = function(ev,slider) {
	ev = ev ? ev : event;
	slider.style.cursor = 'pointer';

	this.dragging = true;
	if (typeof this.onmousedown == 'function') {
		this.onmousedown(slider);
	}
};

TileLayerControl.prototype.sliderMouseUp = function(ev,slider) {
	ev = ev ? ev : event;
	this.dragging = false;

	var mouseX = ev.layerX ? ev.layerX : ev.offsetX ;
        
        this.moveSlider(mouseX);

	this.overlay.setOpacity(this.value);
	if (typeof this.onmouseup == 'function') {
		this.onmouseup(slider);
	}
};


TileLayerControl.prototype.sliderMouseMove = function(ev,slider) {
	ev = ev ? ev : event;
	if (!this.dragging) {
		return;
	}
	slider.style.cursor = 'pointer';
	var mouseX = ev.layerX ? ev.layerX : ev.offsetX ;

        this.moveSlider(mouseX);

	this.overlay.setOpacity(this.value);
	if (typeof this.ondrag == 'function') {
		this.ondrag(this.value);
	}
};

TileLayerControl.prototype.moveSlider = function(mouseX) {

	mouseX = mouseX < (this.knobWidth/2) ? (this.knobWidth/2) : mouseX;
	mouseX = mouseX > this.totalPixels - (this.knobWidth/2) ? this.totalPixels - (this.knobWidth/2) : mouseX;

	this.value = ((mouseX - (this.knobWidth/2)) / (this.totalPixels-this.knobWidth) * 100);

	this.setSlider();

}


TileLayerControl.prototype.setSlider = function() {
	this.label.innerHTML = this.value.toFixed(0) + ' %';
	this.slider.style.backgroundPosition =  (this.value / 100 * (this.totalPixels-this.knobWidth)) + 'px' + ' 0px';
	
//	this.slider.style.backgroundPosition = mouseX - (this.knobWidth/2) + 'px' + ' 0px';
};





