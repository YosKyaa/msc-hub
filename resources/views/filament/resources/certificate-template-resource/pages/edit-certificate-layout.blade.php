<x-filament-panels::page>
<style>
@foreach($record->fonts ?? [] as $font)
@font-face { font-family:'{{ pathinfo($font, PATHINFO_FILENAME) }}'; src:url('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($font) }}') format('truetype'); font-display:swap; }
@endforeach
.cert-editor{display:flex;flex-direction:column;gap:16px;color:rgb(17 24 39)}
.dark .cert-editor{color:rgb(243 244 246)}
.cert-toolbar{position:sticky;top:8px;z-index:30;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px solid rgb(229 231 235);border-radius:12px;background:rgba(255,255,255,.96);box-shadow:0 2px 8px rgba(15,23,42,.08);backdrop-filter:blur(8px)}
.dark .cert-toolbar,.dark .cert-panel{background:rgb(17 24 39);border-color:rgb(55 65 81)}
.cert-toolbar-group{display:flex;flex-wrap:wrap;gap:8px}
.cert-btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border:1px solid rgb(209 213 219);border-radius:8px;background:#fff;color:rgb(55 65 81);font-size:13px;font-weight:600;line-height:1;cursor:pointer;transition:.15s}
.cert-btn:hover{border-color:rgb(245 158 11);background:rgb(255 251 235)}
.cert-btn-primary{border-color:rgb(217 119 6);background:rgb(217 119 6);color:#fff}.cert-btn-primary:hover{background:rgb(180 83 9);color:#fff}.cert-btn:disabled{opacity:.55;cursor:wait}
.dark .cert-btn{background:rgb(31 41 55);border-color:rgb(75 85 99);color:rgb(229 231 235)}
.cert-layout{display:grid;grid-template-columns:220px minmax(0,1fr) 300px;gap:18px;align-items:start}
.cert-panel{padding:14px;border:1px solid rgb(229 231 235);border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.cert-panel-title{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px;font-size:14px;font-weight:700}
.cert-layer-list{display:flex;flex-direction:column;gap:5px}.cert-layer{display:flex;width:100%;align-items:center;gap:8px;padding:9px;border:1px solid transparent;border-radius:8px;background:transparent;text-align:left;cursor:pointer}.cert-layer:hover{background:rgb(249 250 251)}.cert-layer.is-active{border-color:rgb(251 191 36);background:rgb(255 251 235);color:rgb(146 64 14)}
.cert-workspace{min-width:0}.cert-stage{overflow:auto;padding:28px;border:1px solid rgb(203 213 225);border-radius:12px;background:rgb(226 232 240)}.dark .cert-stage{background:rgb(3 7 18);border-color:rgb(55 65 81)}
.cert-canvas{position:relative;margin:0 auto;overflow:hidden;background-color:#fff;box-shadow:0 18px 45px rgba(15,23,42,.24);user-select:none;-webkit-user-select:none}
.cert-element{position:absolute;display:flex;align-items:center;overflow:visible;box-sizing:border-box;touch-action:none;cursor:move}.cert-element.is-editing{outline:2px solid rgb(37 99 235)}.cert-element.is-idle{outline:1px dashed rgba(37,99,235,.55)}.cert-element.is-idle:hover{outline:2px solid rgb(37 99 235)}
.cert-element-content{width:100%;overflow:hidden}.cert-resize{position:absolute;right:-8px;bottom:-8px;width:17px;height:17px;padding:0;border:2px solid white;border-radius:50%;background:rgb(37 99 235);box-shadow:0 1px 4px rgba(0,0,0,.35);cursor:nwse-resize}
.cert-qr{display:flex;height:100%;align-items:center;justify-content:center;background:#fff;text-align:center;font-size:10px;font-weight:700;color:rgb(17 24 39)}
.cert-help{margin-top:9px;text-align:center;font-size:12px;color:rgb(107 114 128)}
.cert-properties{display:flex;flex-direction:column;gap:14px}.cert-properties-header{display:flex;align-items:center;justify-content:space-between}.cert-icon-actions{display:flex;gap:4px}.cert-icon-btn{width:32px;height:32px;border:0;border-radius:7px;background:transparent;cursor:pointer}.cert-icon-btn:hover{background:rgb(243 244 246)}
.cert-field{display:block;font-size:12px;font-weight:600;color:rgb(75 85 99)}.dark .cert-field{color:rgb(209 213 219)}.cert-field input,.cert-field select,.cert-field textarea{display:block;width:100%;margin-top:5px;padding:8px 9px;border:1px solid rgb(209 213 219);border-radius:8px;background:#fff;color:rgb(17 24 39);font:inherit;box-sizing:border-box}.dark .cert-field input,.dark .cert-field select,.dark .cert-field textarea{background:rgb(31 41 55);border-color:rgb(75 85 99);color:white}
.cert-fields-2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.cert-divider{padding-top:12px;border-top:1px solid rgb(229 231 235)}.cert-align{display:grid;grid-template-columns:repeat(3,1fr);gap:5px}.cert-align button{padding:8px 4px;border:1px solid rgb(209 213 219);background:#fff;font-size:12px;cursor:pointer}.cert-align button.is-active{border-color:rgb(245 158 11);background:rgb(255 251 235)}
.cert-empty{padding:18px;text-align:center;font-size:13px;line-height:1.5;color:rgb(107 114 128)}
@media(max-width:1279px){.cert-layout{grid-template-columns:minmax(0,1fr) 290px}.cert-layers{grid-column:1/-1;order:3}.cert-workspace{order:1}.cert-properties-wrap{order:2}.cert-layer-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}}
@media(max-width:800px){.cert-layout{display:flex;flex-direction:column}.cert-workspace,.cert-properties-wrap,.cert-layers{width:100%}.cert-stage{padding:10px}.cert-toolbar{position:static}.cert-toolbar-group{width:100%}.cert-btn{flex:1}.cert-fields-2{grid-template-columns:1fr 1fr}}
</style>
<div x-data="certificateEditor(@js($elements), {{ $record->canvas_width }}, {{ $record->canvas_height }})" x-init="init()" class="cert-editor">
    {{-- Toolbar --}}
    <div class="cert-toolbar">
        <div class="cert-toolbar-group">
            <button type="button" @click="addElement('recipient_name')" class="cert-btn cert-btn-primary">+ Nama</button>
            <button type="button" @click="addElement('recipient_role')" class="cert-btn">+ Peran</button>
            <button type="button" @click="addElement('event_name')" class="cert-btn">+ Kegiatan</button>
            <button type="button" @click="addElement('certificate_number')" class="cert-btn">+ Nomor</button>
            <button type="button" @click="addElement('qr_code')" class="cert-btn">+ QR</button>
            <button type="button" @click="addElement('custom_text')" class="cert-btn">+ Teks</button>
        </div>
        <div class="cert-toolbar-group">
            <button type="button" @click="togglePreview()" class="cert-btn" x-text="preview ? 'Kembali edit' : 'Preview bersih'"></button>
            <button type="button" @click="save()" :disabled="saving" class="cert-btn cert-btn-primary" x-text="saving ? 'Menyimpan...' : 'Simpan template'"></button>
        </div>
    </div>

    <div class="cert-layout">
        {{-- Layers --}}
        <aside class="cert-layers">
            <div class="cert-panel">
                <div class="cert-panel-title"><h3>Layers</h3><span x-text="elements.length + ' elemen'"></span></div>
                <template x-if="elements.length === 0"><p class="rounded-lg bg-gray-50 p-3 text-xs leading-5 text-gray-500">Tambahkan nama, peran, QR, atau teks dari toolbar. Tidak perlu menghitung koordinat.</p></template>
                <div class="cert-layer-list">
                    <template x-for="(element,index) in elements" :key="element.id">
                        <button type="button" @click="selected=index" class="cert-layer" :class="selected===index && 'is-active'">
                            <span class="min-w-0 flex-1 truncate" x-text="element.label"></span>
                            <span class="text-xs text-gray-400" x-text="index+1"></span>
                        </button>
                    </template>
                </div>
            </div>
        </aside>

        {{-- Canvas workspace --}}
        <section class="cert-workspace">
            <div class="cert-stage">
                <div x-ref="canvas" @pointerdown.self="selected=-1" class="cert-canvas"
                    style="width:min(100%, {{ $record->canvas_width }}px);aspect-ratio:{{ $record->canvas_width }}/{{ $record->canvas_height }};background-image:url('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($record->background_path) }}');background-size:100% 100%;background-repeat:no-repeat;">
                    <template x-for="(element,index) in elements" :key="element.id">
                        <div @pointerdown.prevent="preview || startDrag($event,index)" class="cert-element"
                            :class="!preview && selected===index ? 'is-editing' : (!preview ? 'is-idle' : '')"
                            :style="elementStyle(element)">
                            <div class="cert-element-content" :style="element.variable==='qr_code' ? 'height:100%' : ''">
                                <template x-if="element.variable==='qr_code'"><div class="cert-qr">QR<br>CODE</div></template>
                                <template x-if="element.variable!=='qr_code'"><div x-text="previewValue(element)"></div></template>
                            </div>
                            <button x-show="!preview && selected===index" @pointerdown.stop.prevent="startResize($event,index)" type="button" class="cert-resize" aria-label="Ubah ukuran"></button>
                        </div>
                    </template>
                </div>
            </div>
            <p class="cert-help">Drag untuk memindahkan · tarik titik biru untuk resize · gunakan arrow key untuk posisi presisi</p>
        </section>

        {{-- Properties --}}
        <aside class="cert-properties-wrap">
            <template x-if="selected>=0 && elements[selected]">
                <div class="cert-panel cert-properties">
                    <div class="cert-properties-header"><h3>Properti elemen</h3><div class="cert-icon-actions"><button type="button" @click="duplicateSelected()" class="cert-icon-btn" title="Duplikat">⧉</button><button type="button" @click="removeSelected()" class="cert-icon-btn" title="Hapus">×</button></div></div>
                    <label class="cert-field">Variabel
                        <select x-model="elements[selected].variable" @change="syncLabel()" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="recipient_name">Nama penerima</option><option value="recipient_role">Peran</option><option value="certificate_number">Nomor sertifikat</option><option value="event_name">Nama kegiatan</option><option value="event_date">Tanggal kegiatan</option><option value="organizer">Penyelenggara</option><option value="signatory_name">Nama penandatangan</option><option value="signatory_title">Jabatan penandatangan</option><option value="verification_url">URL verifikasi</option><option value="qr_code">QR verifikasi</option><option value="custom_text">Teks statis</option>
                        </select>
                    </label>
                    <label x-show="elements[selected].variable==='custom_text'" class="cert-field">Isi teks<textarea x-model="elements[selected].text" rows="2"></textarea></label>
                    <div class="cert-fields-2">
                        <label class="cert-field">Font<select x-model="elements[selected].font_family"><option>DejaVu Sans</option><option>serif</option><option>sans-serif</option>@foreach($record->fonts ?? [] as $font)<option>{{ pathinfo($font, PATHINFO_FILENAME) }}</option>@endforeach</select></label>
                        <label class="cert-field">Ukuran<input type="number" min="6" max="200" x-model.number="elements[selected].font_size"></label>
                        <label class="cert-field">Ketebalan<select x-model.number="elements[selected].font_weight"><option value="400">Regular</option><option value="600">Semi Bold</option><option value="700">Bold</option></select></label>
                        <label class="cert-field">Warna<input type="color" x-model="elements[selected].color"></label>
                    </div>
                    <div><p class="cert-field">Perataan</p><div class="cert-align"><button type="button" @click="elements[selected].align='left'" :class="elements[selected].align==='left'&&'is-active'">Kiri</button><button type="button" @click="elements[selected].align='center'" :class="elements[selected].align==='center'&&'is-active'">Tengah</button><button type="button" @click="elements[selected].align='right'" :class="elements[selected].align==='right'&&'is-active'">Kanan</button></div></div>
                    <div class="cert-fields-2 cert-divider"><label class="cert-field">X<input type="number" x-model.number="elements[selected].x"></label><label class="cert-field">Y<input type="number" x-model.number="elements[selected].y"></label><label class="cert-field">Lebar<input type="number" x-model.number="elements[selected].width"></label><label class="cert-field">Tinggi<input type="number" x-model.number="elements[selected].height"></label></div>
                </div>
            </template>
            <template x-if="selected<0 || !elements[selected]"><div class="cert-panel cert-empty">Pilih elemen pada kanvas untuk mengedit properti.</div></template>
        </aside>
    </div>
</div>

<script>
function certificateEditor(initialElements,width,height){return{
    elements:(initialElements||[]).map((e,i)=>({...e,id:e.id||crypto.randomUUID()})),selected:-1,preview:false,operation:null,saving:false,
    labels:{recipient_name:'Nama penerima',recipient_role:'Peran',certificate_number:'Nomor sertifikat',event_name:'Nama kegiatan',event_date:'Tanggal kegiatan',organizer:'Penyelenggara',signatory_name:'Nama penandatangan',signatory_title:'Jabatan penandatangan',verification_url:'URL verifikasi',qr_code:'QR verifikasi',custom_text:'Teks statis'},
    init(){window.addEventListener('keydown',(e)=>{if(this.selected<0||['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName))return;const el=this.elements[this.selected];if(e.key==='Delete'){this.removeSelected();return}const step=e.shiftKey?10:1;if(e.key==='ArrowLeft')el.x-=step;if(e.key==='ArrowRight')el.x+=step;if(e.key==='ArrowUp')el.y-=step;if(e.key==='ArrowDown')el.y+=step;});},
    addElement(variable){const qr=variable==='qr_code';this.elements.push({id:crypto.randomUUID(),label:this.labels[variable],variable,text:variable==='custom_text'?'Teks Anda':'',x:Math.round(width*.3),y:Math.round(height*.4),width:qr?120:Math.round(width*.4),height:qr?120:60,font_family:'DejaVu Sans',font_size:qr?12:28,font_weight:variable==='recipient_name'?700:400,align:'center',color:'#111827'});this.selected=this.elements.length-1;this.preview=false;},
    syncLabel(){this.elements[this.selected].label=this.labels[this.elements[this.selected].variable]},
    previewValue(e){return({recipient_name:'Nama Penerima',recipient_role:'Panitia',certificate_number:'CERT-2026-0001',event_name:'Nama Kegiatan',event_date:'12 Juli 2026',organizer:'Jakarta Global University',signatory_name:'Nama Penandatangan',signatory_title:'Jabatan',verification_url:'msc.jgu.ac.id/verify/...',custom_text:e.text})[e.variable]||e.variable},
    elementStyle(e){const scale=this.$refs.canvas?this.$refs.canvas.clientWidth/width:1;return`left:${e.x/width*100}%;top:${e.y/height*100}%;width:${e.width/width*100}%;height:${e.height/height*100}%;font-size:${Math.max(6,e.font_size*scale)}px;font-family:${e.font_family};font-weight:${e.font_weight};color:${e.color};text-align:${e.align};line-height:1.2;`},
    metrics(){const r=this.$refs.canvas.getBoundingClientRect();return{sx:width/r.width,sy:height/r.height}},
    startDrag(ev,index){this.selected=index;const m=this.metrics(),e=this.elements[index];this.operation={type:'drag',index,startX:ev.clientX,startY:ev.clientY,x:e.x,y:e.y,...m};this.listen()},
    startResize(ev,index){const m=this.metrics(),e=this.elements[index];this.operation={type:'resize',index,startX:ev.clientX,startY:ev.clientY,width:e.width,height:e.height,...m};this.listen()},
    listen(){this.onMove=e=>this.move(e);this.onStop=()=>this.stop();window.addEventListener('pointermove',this.onMove);window.addEventListener('pointerup',this.onStop,{once:true})},
    move(ev){const o=this.operation;if(!o)return;const e=this.elements[o.index];if(o.type==='drag'){e.x=Math.max(0,Math.min(width-e.width,Math.round(o.x+(ev.clientX-o.startX)*o.sx)));e.y=Math.max(0,Math.min(height-e.height,Math.round(o.y+(ev.clientY-o.startY)*o.sy)))}else{e.width=Math.max(30,Math.min(width-e.x,Math.round(o.width+(ev.clientX-o.startX)*o.sx)));e.height=Math.max(20,Math.min(height-e.y,Math.round(o.height+(ev.clientY-o.startY)*o.sy)))}},
    stop(){this.operation=null;window.removeEventListener('pointermove',this.onMove)},duplicateSelected(){if(this.selected<0)return;const copy={...this.elements[this.selected],id:crypto.randomUUID(),x:this.elements[this.selected].x+15,y:this.elements[this.selected].y+15};this.elements.push(copy);this.selected=this.elements.length-1},removeSelected(){if(this.selected<0)return;this.elements.splice(this.selected,1);this.selected=Math.min(this.selected,this.elements.length-1)},togglePreview(){this.preview=!this.preview;if(this.preview)this.selected=-1},async save(){this.saving=true;try{await this.$wire.save(this.elements.map(({id,...e})=>e))}finally{this.saving=false}}
}}
</script>
</x-filament-panels::page>
