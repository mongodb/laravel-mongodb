import{_ as s,c as a,o as n,N as o}from"./chunks/framework.8a749e37.js";const C=JSON.parse('{"title":"Geospatial indexes","description":"","frontmatter":{},"headers":[],"relativePath":"v3/guide/schema/geospatial-indexes.md"}'),e={name:"v3/guide/schema/geospatial-indexes.md"},l=o(`<h1 id="geospatial-indexes" tabindex="-1">Geospatial indexes <a class="header-anchor" href="#geospatial-indexes" aria-label="Permalink to &quot;Geospatial indexes&quot;">​</a></h1><p>Geospatial indexes are handy for querying location-based documents.</p><p>They come in two forms: <code>2d</code> and <code>2dsphere</code>. Use the schema builder to add these to a collection.</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#FFCB6B;">Schema</span><span style="color:#89DDFF;">::</span><span style="color:#82AAFF;">create</span><span style="color:#89DDFF;">(</span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">bars</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">,</span><span style="color:#A6ACCD;"> </span><span style="color:#C792EA;">function</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">($</span><span style="color:#A6ACCD;">collection</span><span style="color:#89DDFF;">)</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">{</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">collection</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#82AAFF;">geospatial</span><span style="color:#89DDFF;">(</span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">location</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">,</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">2d</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">);</span></span>
<span class="line"><span style="color:#89DDFF;">});</span></span>
<span class="line"></span></code></pre></div><p>To add a <code>2dsphere</code> index:</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#FFCB6B;">Schema</span><span style="color:#89DDFF;">::</span><span style="color:#82AAFF;">create</span><span style="color:#89DDFF;">(</span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">bars</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">,</span><span style="color:#A6ACCD;"> </span><span style="color:#C792EA;">function</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">($</span><span style="color:#A6ACCD;">collection</span><span style="color:#89DDFF;">)</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">{</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">collection</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#82AAFF;">geospatial</span><span style="color:#89DDFF;">(</span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">location</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">,</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">2dsphere</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">);</span></span>
<span class="line"><span style="color:#89DDFF;">});</span></span>
<span class="line"></span></code></pre></div>`,6),p=[l];function t(c,r,D,F,i,y){return n(),a("div",null,p)}const A=s(e,[["render",t]]);export{C as __pageData,A as default};
