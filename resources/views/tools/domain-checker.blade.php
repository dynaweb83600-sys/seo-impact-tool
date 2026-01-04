<x-app-layout>
<style>
/* scroll horizontal */
.table-scroll{ overflow-x:auto; overflow-y:visible; position:relative; }

.table-scroll table{
  min-width: 1900px;
  width: max-content;
  border-collapse: separate; /* important pour sticky */
  border-spacing: 0;         /* évite les gaps */
}

.table-scroll td,
.table-scroll th { 
  background-clip: padding-box;
}

.sticky-c1, .sticky-c2, .sticky-c3, .sticky-c4,
.sticky-c1h, .sticky-c2h, .sticky-c3h, .sticky-c4h{
  box-shadow: 2px 0 0 rgba(0,0,0,.06); /* séparation visuelle */
}



/* sticky header */
.sticky-head{ position:sticky; top:0; z-index:40; background:#f9fafb; }

/* sticky cols (4 premières) */
.sticky-c1{ position:sticky; left:0;   z-index:35; background:white; }
.sticky-c2{ position:sticky; left:240px; z-index:35; background:white; }
.sticky-c3{ position:sticky; left:380px; z-index:35; background:white; }
.sticky-c4{ position:sticky; left:460px; z-index:35; background:white; }

/* mêmes sticky mais pour le THEAD (au-dessus du body) */
.sticky-c1h{ position:sticky; left:0;   z-index:45; background:#f9fafb; }
.sticky-c2h{ position:sticky; left:240px; z-index:45; background:#f9fafb; }
.sticky-c3h{ position:sticky; left:380px; z-index:45; background:#f9fafb; }
.sticky-c4h{ position:sticky; left:460px; z-index:45; background:#f9fafb; }

/* largeurs fixes pour que les offsets soient corrects */
.col-domain{ width:240px; }
.col-auth{ width:140px; }
.col-rd{ width:80px; }
.col-bl{ width:120px; }

/* tes tooltips */
.tip{ position:relative; display:inline-flex; align-items:center; gap:6px; }
.tip-bubble-fixed{
  position:fixed;
  transform:translateX(-50%);
  min-width:220px;
  max-width:320px;
  background:rgba(17,24,39,.98);
  color:#fff;
  padding:10px 12px;
  border-radius:10px;
  font-size:12px;
  line-height:1.35;
  box-shadow:0 10px 30px rgba(0,0,0,.25);
  z-index:999999;
}
.tip-bubble-fixed:before{
  content:"";
  position:absolute;
  top:-6px;
  left:50%;
  transform:translateX(-50%);
  border-width:0 6px 6px 6px;
  border-style:solid;
  border-color:transparent transparent rgba(17,24,39,.98) transparent;
}
[x-cloak]{ display:none !important; }
</style>

    <div class="w-full max-w-7xl mx-auto p-6" x-data="domainChecker">
        <h1 class="text-2xl font-bold mb-4">
            Domain Authority & Backlinks Checker
        </h1>

        <div class="bg-white rounded-xl shadow p-5">
            <label class="block font-semibold mb-2">
                Domaines (1 par ligne ou séparés par virgule)
            </label>

            <textarea
                class="w-full border rounded-lg p-3"
                rows="5"
                x-model="domains"
                placeholder="example.com&#10;openai.com"></textarea>

            <div class="flex items-center gap-3 mt-4">
			<button
			  class="px-4 py-2 rounded-lg bg-black text-white"
			  @click="runCheck"
			  :disabled="loading"
			  x-text="loading ? 'Analyse…' : 'Analyser'">
			</button>


                <span class="text-sm text-gray-600" x-text="message"></span>
				
				<div class="mt-3" x-show="reportId">
  <div class="flex items-center gap-3">
    <div
      class="h-4 w-4 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"
      x-show="status !== 'done' && status !== 'failed'"
    ></div>
    <div class="text-sm text-gray-700">
      <span x-text="progress"></span>% —
      <span x-text="processedCount"></span>/<span x-text="requestedCount"></span>
    </div>
  </div>

  <div class="w-full bg-gray-200 rounded mt-2 h-2">
    <div class="bg-black h-2 rounded" :style="`width:${progress}%`"></div>
  </div>
</div>



<div class="flex items-center gap-3">
  <span class="text-sm text-gray-500" x-text="status"></span>

  <button
    class="px-3 py-2 rounded-lg border"
    @click="exportCsv"
    x-show="reportId && status === 'done'">
    Exporter CSV
  </button>

  <a href="{{ route('reports.index') }}" class="text-sm underline text-gray-700">
    Historique
  </a>
</div>

				
            </div>
        </div>

        <template x-if="reportId">
<div class="relative -mx-6">
  <div class="px-6">
    <h2 class="text-lg font-semibold">Résultats</h2>
    <span class="text-sm text-gray-500" x-text="status"></span>
  </div>
  
  
<!-- ✅ TABLE (sticky + tooltips + row details) -->
<div class="table-scroll px-6" x-ref="scrollWrap">
  <table class="w-full text-sm border">
    <thead>
      <tr class="border-b bg-gray-50 sticky-head">
        <!-- Domaine -->
        <th class="p-2 text-left whitespace-nowrap col-domain sticky-c1h">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Domaine
            <button type="button"
              x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Domaine">ⓘ</button>

            <template x-teleport="body">
              <div class="tip-bubble-fixed"
                x-cloak
                x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('domain')"
                @mouseenter="open=true"
                @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <!-- Authority -->
        <th class="p-2 text-center whitespace-nowrap col-auth sticky-c2h">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Authority
            <button type="button"
              x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Authority">ⓘ</button>

            <template x-teleport="body">
              <div class="tip-bubble-fixed"
                x-cloak
                x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('authority')"
                @mouseenter="open=true"
                @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <!-- RD -->
        <th class="p-2 text-center whitespace-nowrap col-rd sticky-c3h">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            RD
            <button type="button"
              x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info RD">ⓘ</button>

            <template x-teleport="body">
              <div class="tip-bubble-fixed"
                x-cloak
                x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('rd')"
                @mouseenter="open=true"
                @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <!-- BL -->
        <th class="p-2 text-center whitespace-nowrap col-bl sticky-c4h">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            BL
            <button type="button"
              x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info BL">ⓘ</button>

            <template x-teleport="body">
              <div class="tip-bubble-fixed"
                x-cloak
                x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('bl')"
                @mouseenter="open=true"
                @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Dof
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Dofollow">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('dof')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Nof
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Nofollow">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('nof')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            New/Lost (30j)
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info New/Lost">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('newlost')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            KW
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Keywords">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('kw')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            ETV
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info ETV">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('etv')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Âge
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Âge">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('age')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Dof %
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Dof %">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('dofpct')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            BL/RD
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info BL/RD">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('blrd')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>
		<th class="p-2 text-center whitespace-nowrap">
		  <span class="tip" x-data="{open:false, x:0, y:0}">
			Qualité BL
			<button type="button" x-ref="btn"
			  class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs"
			  @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
			  @mouseleave="open=false">
			  ⓘ
			</button>

			<template x-teleport="body">
			  <div class="tip-bubble-fixed" x-cloak x-show="open"
				:style="`left:${x}px; top:${y}px;`">
				Qualité globale des backlinks.<br>
				Mesure si les liens sont réellement utiles pour le SEO.
			  </div>
			</template>
		  </span>
		</th>
        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Net (30j)
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Net (30j)">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('net30')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Trend
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Trend">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('trend')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>

        <th class="p-2 text-center whitespace-nowrap">
          <span class="tip" x-data="{open:false, x:0, y:0}">
            Valeur
            <button type="button" x-ref="btn"
              class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
              @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              @mouseleave="open=false"
              @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
              aria-label="Info Valeur">ⓘ</button>
            <template x-teleport="body">
              <div class="tip-bubble-fixed" x-cloak x-show="open"
                :style="`left:${x}px; top:${y}px;`"
                x-text="colHelp('value')"
                @mouseenter="open=true" @mouseleave="open=false"></div>
            </template>
          </span>
        </th>
<th class="p-2 text-left whitespace-nowrap">
  <div class="flex items-center gap-3">
    <span class="tip" x-data="{open:false, x:0, y:0}">
      Diagnostic
      <button type="button" x-ref="btn"
        class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full border text-xs text-gray-600 hover:bg-gray-100"
        @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
        @mouseleave="open=false"
        @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
        aria-label="Info Diagnostic">ⓘ</button>

      <template x-teleport="body">
        <div class="tip-bubble-fixed" x-cloak x-show="open"
          :style="`left:${x}px; top:${y}px;`"
          x-text="colHelp('diagnostic')"
          @mouseenter="open=true" @mouseleave="open=false"></div>
      </template>
    </span>

    <!-- Tooltip contenu -->
    <span class="tip" x-data="{open:false, x:0, y:0}">
      <button type="button" x-ref="btn"
        class="inline-flex items-center justify-center px-2 py-1 rounded border text-xs text-gray-700 hover:bg-gray-100"
        @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
        @mouseleave="open=false"
        @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;">
        Pages vs Articles
      </button>


	
      <template x-teleport="body">
        <div class="tip-bubble-fixed" x-cloak x-show="open"
          :style="`left:${x}px; top:${y}px;`"
          x-text="colHelp('content_pages')"
          @mouseenter="open=true" @mouseleave="open=false"></div>
      </template>
    </span>
		


    <!-- Tooltip backlinks -->
    <span class="tip" x-data="{open:false, x:0, y:0}">
      <button type="button" x-ref="btn"
        class="inline-flex items-center justify-center px-2 py-1 rounded border text-xs text-gray-700 hover:bg-gray-100"
        @mouseenter="open=true; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;"
        @mouseleave="open=false"
        @click="open=!open; const r=$refs.btn.getBoundingClientRect(); x=r.left+(r.width/2); y=r.bottom+10;">
        Backlinks
      </button>

      <template x-teleport="body">
        <div class="tip-bubble-fixed" x-cloak x-show="open"
          :style="`left:${x}px; top:${y}px;`"
          x-text="colHelp('backlinks')"
          @mouseenter="open=true" @mouseleave="open=false"></div>
      </template>
    </span>

  </div>
</th>


      </tr>
    </thead>

<tbody>
  <template x-for="row in sortedItems()" :key="row.id">
    <!-- ligne principale -->
    <tr class="border-b">
      <td class="p-2 sticky-c1 col-domain" x-text="row.domain"></td>

      <td class="p-2 text-center sticky-c2 col-auth">
        <div class="inline-flex items-center gap-2">
          <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-gray-800"
                x-text="authorityScore(row)"></span>
          <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold"
                :class="authorityLabelClass(row)"
                x-text="authorityLabel(row)"></span>
        </div>
      </td>

      <td class="p-2 text-center sticky-c3 col-rd" x-text="row.linking_domains ?? '—'"></td>
      <td class="p-2 text-center sticky-c4 col-bl" x-text="row.inbound_links ?? '—'"></td>

      <td class="p-2 text-center" x-text="row.dofollow_links ?? '—'"></td>
      <td class="p-2 text-center" x-text="row.nofollow_links ?? '—'"></td>

      <td class="p-2 text-center"
          x-text="(row.new_backlinks_30d ?? '—') + ' / ' + (row.lost_backlinks_30d ?? '—')"></td>

      <td class="p-2 text-center" x-text="row.organic_keywords ?? '—'"></td>
      <td class="p-2 text-center" x-text="row.traffic_etv !== null ? Number(row.traffic_etv).toFixed(2) : '—'"></td>

      <td class="p-2 text-center">
        <template x-if="row.domain_age_years_rounded !== null">
          <span x-text="row.domain_age_years_rounded + ' ans (' + (row.domain_age_label ?? '—') + ')'"></span>
        </template>
        <template x-if="row.domain_age_years_rounded === null">
          <span>—</span>
        </template>
      </td>

      <td class="p-2 text-center" x-text="row.dofollow_ratio !== null ? row.dofollow_ratio + '%' : '—'"></td>
	  <td class="p-2 text-center" x-text="row.backlinks_per_ref_domain !== null ? row.backlinks_per_ref_domain : '—'"></td>
      	  	  <td class="p-2 text-center">
  <template x-if="row.linking_domains > 0">
    <div class="inline-flex items-center gap-2">
      <span
        class="px-2 py-1 rounded text-sm font-semibold"
        :class="backlinksQualityClass(backlinksQualityScore(row))"
        x-text="backlinksQualityScore(row)">
      </span>
      <span
        class="px-2 py-1 rounded text-xs"
        :class="backlinksQualityClass(backlinksQualityScore(row))"
        x-text="backlinksQualityLabel(backlinksQualityScore(row))">
      </span>
    </div>
  </template>

  <template x-if="!row.linking_domains">
    <span class="text-gray-400">—</span>
  </template>
</td>
	  <td class="p-2 text-center" x-text="row.net_backlinks_30d !== null ? row.net_backlinks_30d : '—'"></td>
	  <td class="p-2 text-center" x-text="row.growth_trend ?? '—'"></td>
      <td class="p-2 text-center" x-text="row.estimated_seo_value_text ?? (row.estimated_seo_value_eur !== null ? Number(row.estimated_seo_value_eur).toFixed(2) + ' €' : '—')"></td>

	<!-- Diagnostic court -->
	<td class="p-2 align-top">
	  <div class="flex items-center gap-2 mb-2">
		<button
		  class="px-2 py-1 rounded border text-xs hover:bg-gray-100"
		  @click="loadContentSuggestions(row)">
		  Generer un contenu pour vos pages et articles manquants
		</button>
	  </div>
	
	  <template x-if="row.seo_diagnosis && row.seo_diagnosis.length">
		<ul class="list-disc pl-4 space-y-1">
		  <template x-for="d in row.seo_diagnosis" :key="d.message">
			<li>
			  <span class="font-semibold" x-text="d.message"></span>
			  <span class="text-gray-600" x-text="' — ' + d.action"></span>
			</li>
		  </template>
		</ul>
	  </template>

	  <template x-if="!row.seo_diagnosis || !row.seo_diagnosis.length">
		<span class="text-gray-500">RAS</span>
	  </template>
	  
	  
	  	  	<!-----------------------------------------BACKLINK----------------------------------------------------------------->

<!-- BACKLINK -->
<template x-if="needsBacklinks(row)">
  <div class="mt-3 border rounded-xl p-4 bg-gray-50">
    <div class="font-semibold text-gray-900">Backlinks recommandés</div>
    <div class="text-sm text-gray-600 mt-1">
      <button class="underline" @click="backlinksHelpOpen = true; loadBacklinksAdvice(row);">
        Voir recommandations backlinks
      </button>
    </div>
  </div>
</template>


	<!-----------------------------------------FINBACKLINK-------------------------------------------------------------->
	  
	</td>
    </tr>

    <!-- ligne détails -->
    <tr class="border-b bg-gray-50" x-show="(row.seo_brief && row.seo_brief.length)" x-cloak>
      <td class="p-3" colspan="16">
        <div class="space-y-2">
          <template x-for="b in row.seo_brief" :key="b.title">
            <div class="border rounded-lg p-3 bg-white">
              <div class="font-semibold text-sm" x-text="b.title"></div>
              <div class="text-xs text-gray-600 italic mt-1" x-text="b.why"></div>
              <ul class="list-disc pl-5 text-xs mt-2 space-y-1">
                <template x-for="r in b.recommendation" :key="r">
                  <li x-text="r"></li>
                </template>
              </ul>
            </div>
          </template>
        </div>
		
		
      </td>

    </tr>

  </template>   
</tbody>


  </table>
</div>

 <div x-show="contentPanel.open" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-end md:items-center justify-center">
  <div class="bg-white w-full md:max-w-4xl rounded-t-2xl md:rounded-2xl p-4 shadow-xl max-h-[85vh] overflow-auto">
    <div class="flex items-center justify-between">
      <div class="font-semibold">
        Suggestions contenu — <span class="text-gray-600" x-text="contentPanel.domain"></span>
      </div>
      <button class="text-sm underline" @click="contentPanel.open=false">Fermer</button>
    </div>

    <template x-if="contentPanel.loading">
      <div class="py-6 text-sm text-gray-600">Chargement…</div>
    </template>

    <template x-if="contentPanel.error">
      <div class="py-4 text-sm text-red-600" x-text="contentPanel.error"></div>
    </template>
	
	<template x-if="!contentPanel.loading && !contentPanel.error && !contentPanel.pages.length && !contentPanel.articles.length">
	  <div class="py-6 text-sm text-gray-600">Aucune suggestion.</div>
	</template>


    <div class="grid md:grid-cols-2 gap-4" x-show="!contentPanel.loading && !contentPanel.error">
      <div>
        <div class="font-semibold mb-2">Pages (priorité)</div>
        <template x-for="p in contentPanel.pages" :key="p.id">
          <div class="border rounded-lg p-3 mb-2">
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-1 rounded bg-gray-100" x-text="p.priority_label"></span>
              <span class="text-xs px-2 py-1 rounded bg-gray-100" x-text="p.intent"></span>
              <span class="text-xs px-2 py-1 rounded bg-gray-100" x-text="p.priority_score"></span>
            </div>
            <div class="font-semibold mt-2" x-text="p.suggested_title"></div>
            <div class="text-xs text-gray-600 mt-1" x-text="p.primary_keyword"></div>
			<div class="mt-3 flex gap-2">
		  <button
			class="px-3 py-2 rounded-lg bg-black text-white text-xs"
			@click="generateSuggestion(p.id)">
			Générer
		  </button>

		  <button
			class="px-3 py-2 rounded-lg border text-xs"
			x-show="p.generated_html"
			@click="openGenerated(p)">
			Voir
		  </button>
		</div>

		<div class="mt-3 text-xs text-green-700" x-show="p.generated_at">
		  Généré le <span x-text="p.generated_at"></span>
		</div>

            <ul class="list-disc pl-5 text-xs mt-2 space-y-1">
              <template x-for="h in (p.outline_h2 || [])" :key="h">
                <li x-text="h"></li>
              </template>
            </ul>
          </div>
        </template>
      </div>

      <div>
        <div class="font-semibold mb-2">Articles (support)</div>
        <template x-for="a in contentPanel.articles" :key="a.id">
          <div class="border rounded-lg p-3 mb-2">
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-1 rounded bg-gray-100" x-text="a.priority_label"></span>
              <span class="text-xs px-2 py-1 rounded bg-gray-100" x-text="a.intent"></span>
              <span class="text-xs px-2 py-1 rounded bg-gray-100" x-text="a.priority_score"></span>
            </div>
            <div class="font-semibold mt-2" x-text="a.suggested_title"></div>
            <div class="text-xs text-gray-600 mt-1" x-text="a.primary_keyword"></div>
			<div class="mt-3 flex gap-2">
		  <button
			class="px-3 py-2 rounded-lg bg-black text-white text-xs"
			@click="generateSuggestion(a.id)">
			Générer
		  </button>

		  <button
			class="px-3 py-2 rounded-lg border text-xs"
			x-show="a.generated_html"
			@click="openGenerated(a)">
			Voir
		  </button>
		</div>

		<div class="mt-3 text-xs text-green-700" x-show="a.generated_at">
		  Généré le <span x-text="a.generated_at"></span>
		</div>
            <ul class="list-disc pl-5 text-xs mt-2 space-y-1">
              <template x-for="h in (a.outline_h2 || [])" :key="h">
                <li x-text="h"></li>
              </template>
            </ul>
          </div>
        </template>
      </div>
    </div>











	<!-- ✅ Preview generated content (no popup) -->
<div x-show="contentPanel.previewOpen" x-cloak class="mt-4 border rounded-lg p-3 bg-gray-50">
  <div class="flex items-center justify-between mb-2">
    <div class="font-semibold text-sm">Aperçu du contenu généré</div>
    <button class="text-sm underline" @click="contentPanel.previewOpen=false">Fermer</button>
  </div>

  <div class="prose max-w-none text-sm bg-white border rounded-lg p-3 overflow-auto max-h-[50vh]"
       x-html="contentPanel.previewHtml"></div>
</div>



  </div>
  
  
  
  
  
  
</div>


            </div>
        </template>
		
		
		<!----------------------------------------------------------------DEBUT---------------------------------------------->
		<!-- ✅ Bloc : Acheter / Obtenir des backlinks (Linkuma + alternatives) -->
		<!-- ✅ Bloc Backlinks dynamique (GPT) -->
		<div
		  id="backlinks-help"
		  x-show="backlinksHelpOpen"
		  x-cloak
		  class="mt-10 bg-white rounded-2xl shadow p-6"
		>
		  <button
			type="button"
			@click="backlinksHelpOpen = false"
			class="text-sm text-gray-500 hover:text-black"
		  >
			Fermer ✕
		  </button>
		  <div class="text-sm text-gray-500 mt-2">
			Domaine : <span class="font-semibold" x-text="backlinksDomain"></span>
		 </div>
	
		  <div class="mt-4">
			<template x-if="backlinksLoading">
			  <div class="text-sm text-gray-500">
				Analyse des besoins en backlinks…
			  </div>
			</template>

			<template x-if="!backlinksLoading">
			  <div
				class="prose max-w-none text-sm"
				x-html="backlinksHtml"
			  ></div>
			</template>
		  </div>
		</div>

		
		<!----------------------------------------------------------------FIN------------------------------------------------>
		
    </div>

    <script>
document.addEventListener('alpine:init', () => {
  Alpine.data('domainChecker', () => ({
    requestedCount: 0,
    processedCount: 0,
    progress: 0,
    domains: '',
    loading: false,
    message: '',
    reportId: null,
    token: null,
    status: 'pending',
    items: [],
    poll: null,
	backlinksHelpOpen: false,
	backlinksLoading: false,
	backlinksHtml: '',
	backlinksDomain: '',

	
// =========================
// AUTHORITY COMPOSITE (0–100) + courbe "Moz-like"
// =========================
clamp01(v) { return Math.max(0, Math.min(1, v)); },
clamp100(v) { return Math.max(0, Math.min(100, v)); },

contentPanel: {
  open: false,
  domain: null,
  pages: [],
  articles: [],
  loading: false,
  error: null,
    // ✅ preview
  previewOpen: false,
  previewHtml: '',
},
buildSeoBrief(row) {
  const briefs = [];

  const authority = this.authorityScoreNumber(row);
  const kw = Number(row.organic_keywords ?? 0);
  const etv = Number(row.traffic_etv ?? 0);
  const rd = Number(row.linking_domains ?? 0);


  // 🔹 Cas 1 : Site faible / peu visible
  if (kw < 50 && authority < 40) {
    briefs.push({
      title: "🔥 Brief SEO – Création de contenu",
      intent: "informationnelle",
      recommendation: [
        "Créer 1 page pilier (800 à 1200 mots)",
        "Ajouter 5 à 8 sections (H2)",
        "Répondre aux questions fréquentes des clients",
        "Ajouter une FAQ en bas de page"
      ],
      why: "Le site manque de contenu visible sur Google"
    });
  }

  // 🔹 Cas 2 : Site moyen mais sous-exploité
  if (kw >= 50 && kw < 500 && authority >= 40) {
    briefs.push({
      title: "🔥 Brief SEO – Renforcement sémantique",
      intent: "mixte (info + conversion)",
      recommendation: [
        "Créer 2 à 3 articles complémentaires",
        "Renforcer les pages existantes (+400 mots)",
        "Optimiser les titres (H1/H2)",
        "Ajouter des liens internes"
      ],
      why: "Le site a du potentiel mais manque de profondeur sémantique"
    });
  }

  // 🔹 Cas 3 : Trafic mais peu de valeur
  if (etv < 100 && kw > 100) {
    briefs.push({
      title: "🔥 Brief SEO – Conversion",
      intent: "commerciale",
      recommendation: [
        "Créer une page service / produit optimisée",
        "Ajouter des appels à l’action",
        "Structurer avec bénéfices + preuves",
        "Optimiser le maillage interne"
      ],
      why: "Le trafic existe mais ne génère pas assez de valeur"
    });
  }

  return briefs;
},

logScore(x, target) {
  x = Number(x ?? 0);
  if (x <= 0) return 0;
  // 0..100 (pas de *2 ici)
  const s = (Math.log10(x + 1) / Math.log10(target + 1)) * 100;
  return this.clamp100(s);
},

linearScore(x, min, max) {
  x = Number(x ?? 0);
  if (max <= min) return 0;
  return this.clamp100(this.clamp01((x - min) / (max - min)) * 100);
},

scoreLinks(row) {
  const rd = Number(row.linking_domains ?? 0);
  const bl = Number(row.inbound_links ?? 0);
  const dofPct = Number(row.dofollow_ratio ?? 0);

  // RD = facteur principal (beaucoup plus proche de Ahrefs/Moz)
  const sRD = this.logScore(rd, 6000); // 6k RD ~ gros site

  // BL/RD : seulement un "petit signal"
  // - on pénalise un peu si c'est extrêmement bas (profil pauvre)
  // - on NE pénalise PAS si c'est très haut (cas gros sites)
  const blrd = (rd > 0) ? (bl / rd) : 0;
  let sBLRD = 70; // neutre par défaut

  if (blrd > 0 && blrd < 1.5) {
    // très peu de liens par domaine référent -> profil faible
    sBLRD = 40 + this.linearScore(blrd, 0, 1.5) * 30; // 40..70
  } else if (blrd >= 1.5 && blrd <= 20) {
    // zone OK
    sBLRD = 70 + this.linearScore(blrd, 1.5, 20) * 20; // 70..90
  } else {
    // au-delà : on reste neutre (surtout pas de grosse pénalité)
    sBLRD = 75;
  }

  // Dofollow% : petit bonus
  const sDof = 60 + this.linearScore(dofPct, 5, 95) * 40;

  // Pondération : RD domine
  return this.clamp100(
    (sRD * 0.80) +
    (sBLRD * 0.10) +
    (sDof * 0.10)
  );
},

scoreSeoVisibility(row) {
  const kw  = Number(row.organic_keywords ?? 0);
  const etv = Number(row.traffic_etv ?? 0);

  // Targets volontairement "durs" pour éviter les scores trop hauts
  const sKW  = this.logScore(kw, 5000);
  const sETV = this.logScore(etv, 2000);

  return this.clamp100((sKW * 0.65) + (sETV * 0.35));
},

scoreTrust(row) {
  const age = Number(row.domain_age_years ?? row.domain_age_years_rounded ?? 0);
  return this.logScore(age, 10); // 10 ans => 100
},

scoreMomentum(row) {
  const net = Number(row.net_backlinks_30d ?? 0);
  return this.clamp100(50 + (net * 1.2)); // moins agressif que 2.5
},

authorityRaw(row) {
  const sLinks = this.scoreLinks(row);
  const sSEO   = this.scoreSeoVisibility(row);
  const sTrust = this.scoreTrust(row);
  const sMom   = this.scoreMomentum(row);

  const raw =
    (sLinks * 0.45) +
    (sSEO   * 0.25) +
    (sTrust * 0.20) +
    (sMom   * 0.10);

  return this.clamp100(raw);
},

authorityComposite(row) {
  const raw = this.authorityRaw(row);

  // courbe "Moz-like": écrase les scores moyens
  const gamma = 3.4; // ajuste entre 3.0 et 4.0 selon ton ressenti
  const final = 100 * Math.pow(raw / 100, gamma);

  return Math.round(this.clamp100(final));
},

authorityScoreNumber(row) {
  const rd = Number(row?.linking_domains ?? 0);
  if (!rd || rd <= 0) return null;
  return this.authorityComposite(row);
},

authorityScore(row) {
  const s = this.authorityScoreNumber(row);
  return (s === null || s === undefined) ? '—' : s;
},	

authorityLabel(row) {
  const s = this.authorityScoreNumber(row);
  if (s === null) return '—';

  if (s < 20) return 'Très faible';
  if (s < 35) return 'Faible';
  if (s < 50) return 'Correct';
  if (s < 65) return 'Bon';
  if (s < 80) return 'Très fort';
  return 'Exceptionnel';
},


authorityLabelClass(row) {
  const s = this.authorityScoreNumber(row);
  if (s === null) return 'bg-gray-100 text-gray-600';

  if (s < 20) return 'bg-red-200 text-red-900';
  if (s < 35) return 'bg-red-100 text-red-800';
  if (s < 50) return 'bg-yellow-100 text-yellow-800';
  if (s < 65) return 'bg-blue-100 text-blue-800';
  if (s < 80) return 'bg-green-100 text-green-800';
  return 'bg-green-200 text-green-900';
},
sortedItems() {
  return [...this.items].sort((a, b) => {
    const sa = this.authorityScoreNumber(a) ?? -1;
    const sb = this.authorityScoreNumber(b) ?? -1;
    return sb - sa; // décroissant
  });
},

// =========================
// BACKLINKS QUALITY SCORE
// =========================

scoreDofQuality(row) {
  const pct = Number(row.dofollow_ratio ?? 0);
  return this.linearScore(pct, 20, 90);
},

scoreRDQuality(row) {
  const rd = Number(row.linking_domains ?? 0);
  return this.logScore(rd, 200);
},

scoreBLRDQuality(row) {
  const rd = Number(row.linking_domains ?? 0);
  const bl = Number(row.inbound_links ?? 0);
  if (!rd) return 0;

  const ratio = bl / rd;

  if (ratio < 1) return 30;
  if (ratio <= 15) return 80;
  if (ratio <= 40) return 60;
  return 40;
},

scoreMomentumQuality(row) {
  const net = Number(row.net_backlinks_30d ?? 0);
  return this.clamp100(50 + net * 2);
},

backlinksQualityScore(row) {
  const sDof = this.scoreDofQuality(row);
  const sRD  = this.scoreRDQuality(row);
  const sBR  = this.scoreBLRDQuality(row);
  const sMo  = this.scoreMomentumQuality(row);

  const score =
    (sDof * 0.35) +
    (sRD  * 0.30) +
    (sBR  * 0.20) +
    (sMo  * 0.15);

  return Math.round(this.clamp100(score));
},

backlinksQualityLabel(score) {
  if (score < 30) return "Très faible";
  if (score < 45) return "Faible";
  if (score < 60) return "Moyenne";
  if (score < 75) return "Bonne";
  return "Excellente";
},

backlinksQualityClass(score) {
  if (score < 30) return "bg-red-200 text-red-900";
  if (score < 45) return "bg-orange-200 text-orange-900";
  if (score < 60) return "bg-yellow-200 text-yellow-900";
  if (score < 75) return "bg-blue-200 text-blue-900";
  return "bg-green-200 text-green-900";
},

colHelp(key) {
  const map = {

    domain: "Nom de domaine analysé.",

    authority: "Score interne (0–100) basé sur les liens, la visibilité SEO, l’ancienneté du domaine et la dynamique récente. Sert à comparer des sites entre eux.",

    rd: "RD (Referring Domains) : nombre de sites différents qui font au moins un lien vers ce domaine.",

    bl: "BL (Backlinks) : nombre total de liens entrants. Un même site peut envoyer plusieurs backlinks.",

    dof: "Dofollow : liens qui transmettent de la popularité SEO (les plus utiles pour le référencement).",

    nof: "Nofollow : liens qui transmettent peu ou pas de popularité SEO, mais restent utiles pour un profil naturel.",

    newlost: "Nouveaux / perdus sur 30 jours : évolution des backlinks sur la période récente.",

    kw: "KW (Keywords) : nombre estimé de mots-clés sur lesquels le site apparaît dans Google.",

    etv: "ETV (Estimated Traffic Value) : valeur estimée du trafic SEO en euros si ce trafic devait être acheté en publicité.",

    age: "Âge du domaine : ancienneté du site. Les domaines plus anciens inspirent généralement plus confiance à Google.",

    dofpct: "Pourcentage de liens dofollow par rapport au total des backlinks.",

    blrd: "BL/RD : moyenne de backlinks par domaine référent. Une valeur trop élevée peut indiquer des liens répétitifs.",

    net30: "Net (30j) : nouveaux backlinks moins backlinks perdus sur les 30 derniers jours.",

    trend: "Tendance SEO calculée à partir de l’évolution récente des backlinks.",

    value: "Valeur SEO estimée du site basée sur son trafic organique.",

    content_pages: `
	Pages SEO vs Articles

	Pages SEO :
	Pages fixes et stratégiques du site (services, catégories, pages piliers).
	Elles servent de fondation au référencement.

	Articles :
	Contenus publiés régulièrement pour répondre à des questions précises.
	Ils attirent du trafic et renforcent les pages SEO.

	Pourquoi ces pages ou ces articles sont necessaire ?
	C’est le minimum efficace pour créer une structure SEO crédible et exploitable par Google.
		`,

		backlinks: `
	Un backlink est une recommandation provenant d’un autre site.

	Faut-il les créer d’un coup ?
	Non. Il est recommandé de les répartir sur 2 à 3 mois pour rester naturel.
	`,

    diagnostic: "Conseils prioritaires basés sur les données du site pour améliorer son référencement."
  };

  return map[key] || "";
}
,



buildSeoDiagnosis(row) {
  const diag = [];
  const authority = this.authorityScoreNumber(row);
  const kw = Number(row.organic_keywords ?? 0);
  const rd = Number(row.linking_domains ?? 0);
diag.push({
        message: "En cours de chargement",
        action: ""
      });
  // CONTENU
 /* if (kw < 50) {
    if (authority < 35) {
      diag.push({
        message: "Manque de contenu SEO",
        action: "Créer les bases : 3 pages SEO + 8 articles"
      });
    } else if (authority < 70) {
      diag.push({
        message: "Contenu SEO insuffisant",
        action: "Renforcer le contenu existant (+10–15 pages/articles)"
      });
	   } else {
	  diag.push({
		message: "Votre site peut attirer beaucoup plus de visiteurs",
		action: "Créer 3 à 5 pages importantes qui présentent clairement vos services ou produits"
	  });
	}
  }

  // BACKLINKS
  if (rd < 20) {
    if (authority < 35) {
      diag.push({
        message: "Votre site est peu recommandé sur Internet",
        action: "Obtenir 10 backlinks thématiques de base"
      });
    } else {
      diag.push({
        message: "Votre site pourrait être plus crédible aux yeux de Google avec de meilleurs backlinks",
        action: "Renforcer avec des backlinks qualitatifs"
      });
    }
  }


	const q = this.backlinksQualityScore(row);

	if (q < 40) {
	  diag.push({
		message: "Backlinks peu efficaces",
		action: "Améliorer la qualité et la diversité des backlinks"
	  });
	}*/
  return diag;
},
/* 🔼 FIN 🔼 */


    async runCheck() {
      this.loading = true;
      this.message = '';
      this.items = [];
      this.requestedCount = 0;
      this.processedCount = 0;
      this.progress = 0;

      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      const res = await fetch('/api/check', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf ?? '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ domains: this.domains })
      });

      const ct = res.headers.get('content-type') || '';
      if (!ct.includes('application/json')) {
        const text = await res.text();
        console.log('Non-JSON response:', text);
        this.message = 'Erreur (probable CSRF / session). Rafraîchis la page et réessaie.';
        this.loading = false;
        return;
      }

      const data = await res.json();

      if (!res.ok) {
        this.message = data.message || 'Erreur';
        this.loading = false;
        return;
      }

      this.reportId = data.report_id;
      this.token = data.access_token;
      this.loading = false;

      this.status = 'pending';
      this.startPolling();
    },

    exportCsv() {
      if (!this.reportId || !this.token) return;
      window.location.href = `/api/reports/${this.reportId}/export.csv?token=${this.token}`;
    },

    startPolling() {
      this.poll = setInterval(async () => {
        const res = await fetch(`/api/reports/${this.reportId}?token=${this.token}`);
        const data = await res.json();

        this.status = data.status;
		this.items = (data.items || []).map(row => ({
		  ...row,
		  seo_diagnosis: row.ai_diagnosis?.length ? row.ai_diagnosis : this.buildSeoDiagnosis(row),
		  seo_brief: row.ai_details?.length ? row.ai_details : this.buildSeoBrief(row),
		}));

        this.requestedCount = Math.max(
		  Number(data.requested_count ?? 0),
		  this.items.length
		);

		this.processedCount = Math.max(
		  Number(data.processed_count ?? 0),
		  this.items.length
		);

        this.progress = this.requestedCount
          ? Math.min(100, Math.round((this.processedCount / this.requestedCount) * 100))
          : 0;

        if (data.status === 'done' || data.status === 'failed') {
          clearInterval(this.poll);
        }
      }, 1200);
    },
	async loadContentSuggestions(row) {
	  console.log('ROW CLICK', row);

	  if (!row?.id) {
		this.contentPanel.open = true;
		this.contentPanel.loading = false;
		this.contentPanel.error = "row.id manquant (API /api/reports ne renvoie pas id)";
		return;
	  }

	  this.contentPanel.open = true;
	  this.contentPanel.loading = true;
	  this.contentPanel.error = null;
	  this.contentPanel.domain = row.domain;
	  this.contentPanel.pages = [];
	  this.contentPanel.articles = [];

	  try {
		const url = `/api/report-items/${row.id}/content-suggestions?token=${this.token}`;
		const res = await fetch(url);

		const ct = (res.headers.get('content-type') || '').toLowerCase();
		let data = null;

		if (ct.includes('application/json')) {
		  data = await res.json();
		} else {
		  const text = await res.text();
		  console.warn('Non-JSON response:', text.slice(0, 300));
		  throw new Error(`Réponse non-JSON (${res.status}). Voir console.`);
		}

		console.log('CONTENT API RESPONSE', res.status, data);

		// Si API renvoie {pages:..., articles:...} ou {data:{pages:...}}
		const payload = data?.data ?? data;

		if (!res.ok) {
		  throw new Error(payload?.message || `Erreur API (${res.status})`);
		}

		if (payload?.error) {
		  throw new Error(payload.error);
		}

		this.contentPanel.pages = payload.pages || [];
		this.contentPanel.articles = payload.articles || [];

		// Si vide -> message explicite
		if (!this.contentPanel.pages.length && !this.contentPanel.articles.length) {
		  this.contentPanel.error = "Aucune suggestion retournée par l’API (pages/articles vides).";
		}

	  } catch (e) {
		console.error('loadContentSuggestions error', e);
		this.contentPanel.error = e?.message || 'Erreur';
	  } finally {
		this.contentPanel.loading = false;
		console.log('contentPanel state', JSON.parse(JSON.stringify(this.contentPanel)));
	  }
	},
	async generateSuggestion(suggestionId) {
	  if (!suggestionId) return;

	  try {
		const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
		this.contentPanel.error = null;

		const res = await fetch(`/content-suggestions/${suggestionId}/generate`, {
		  method: 'POST',
		  headers: {
			'Accept': 'application/json',
			'X-CSRF-TOKEN': csrf ?? '',
			'X-Requested-With': 'XMLHttpRequest',
		  },
		  credentials: 'same-origin',
		});

		const ct = (res.headers.get('content-type') || '').toLowerCase();
		const raw = ct.includes('application/json') ? await res.json() : { message: await res.text() };

		if (!res.ok) {
		  throw new Error(raw?.message || `Erreur génération (${res.status})`);
		}

		// ✅ payload peut être {data:{...}} ou {...}
		const payload = raw?.data ?? raw;

		const generatedHtml = payload.generated_html ?? payload.html ?? payload.content ?? null;
		const generatedAt   = payload.generated_at ?? payload.generatedAt ?? new Date().toISOString();

		if (!generatedHtml) {
		  console.warn('Réponse génération:', raw);
		  throw new Error("L'API n'a pas renvoyé generated_html (voir console).");
		}

		const apply = (arr) => arr.map(x => {
		  if (String(x.id) === String(suggestionId)) {
			return {
			  ...x,
			  generated_html: generatedHtml,
			  generated_at: generatedAt,
			};
		  }
		  return x;
		});

		this.contentPanel.pages = apply(this.contentPanel.pages);
		this.contentPanel.articles = apply(this.contentPanel.articles);

		// ✅ ouvre direct
		this.openGenerated({ generated_html: generatedHtml });

	  } catch (e) {
		console.error(e);
		this.contentPanel.error = e?.message || 'Erreur';
	  }
	},
async loadBacklinksAdvice(row) {
  if (!row?.id) return;

  this.backlinksLoading = true;
  this.backlinksHtml = '';
  this.backlinksDomain = row.domain || '';

  try {
    const url = `/api/report-items/${row.id}/backlinks-advice?token=${encodeURIComponent(this.token)}`;

    const res = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });

    const ct = (res.headers.get('content-type') || '').toLowerCase();

    if (!ct.includes('application/json')) {
      const text = await res.text();
      console.warn('Backlinks advice non-JSON response:', res.status, text.slice(0, 300));
      throw new Error(`Réponse non-JSON (${res.status}). Probable redirect/login/erreur serveur.`);
    }

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data?.message || `Erreur API (${res.status})`);
    }

    this.backlinksHtml = data.html || '<p>Aucune recommandation.</p>';
  } catch (e) {
    this.backlinksHtml = `<p style="color:#dc2626">Erreur : ${e.message}</p>`;
  } finally {
    this.backlinksLoading = false;
  }
},
needsBacklinks(row) {
  const authority = Number(row.authority_score ?? this.authorityScoreNumber(row) ?? 0);
  const rd = Number(row.linking_domains ?? 0);
  const bl = Number(row.inbound_links ?? 0);

  return (authority < 35) || (rd < 10) || (bl < 20);
},
openGenerated(item) {
  const html = item.generated_html || '<p>Aucun contenu généré</p>';

  const w = window.open('', '_blank');

  // ✅ Popup bloquée
  if (!w) {
    // Option 1: afficher dans une modale interne
    this.contentPanel.previewOpen = true;
    this.contentPanel.previewHtml = html;
    return;

    // Option 2 (si tu préfères): alerte
    // alert("Popup bloquée par le navigateur. Autorise les popups pour 127.0.0.1 puis réessaie.");
    // return;
  }

  w.document.open();
  w.document.write(html);
  w.document.close();
}



  }));
});
    </script>
</x-app-layout>
