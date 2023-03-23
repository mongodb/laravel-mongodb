import{_ as s,c as a,o as n,N as l}from"./chunks/framework.8a749e37.js";const A=JSON.parse('{"title":"EmbedsOne Relationship","description":"","frontmatter":{},"headers":[],"relativePath":"v3/guide/relationships/embeds-one-relationship.md"}'),p={name:"v3/guide/relationships/embeds-one-relationship.md"},o=l(`<h1 id="embedsone-relationship" tabindex="-1">EmbedsOne Relationship <a class="header-anchor" href="#embedsone-relationship" aria-label="Permalink to &quot;EmbedsOne Relationship&quot;">​</a></h1><p>The embedsOne relation is similar to the embedsMany relation, but only embeds a single model.</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#F78C6C;">use</span><span style="color:#FFCB6B;"> </span><span style="color:#A6ACCD;">Jenssegers</span><span style="color:#89DDFF;">\\</span><span style="color:#A6ACCD;">Mongodb</span><span style="color:#89DDFF;">\\</span><span style="color:#A6ACCD;">Eloquent</span><span style="color:#89DDFF;">\\</span><span style="color:#A6ACCD;">Model</span><span style="color:#89DDFF;">;</span></span>
<span class="line"></span>
<span class="line"><span style="color:#C792EA;">class</span><span style="color:#A6ACCD;"> </span><span style="color:#FFCB6B;">Book</span><span style="color:#A6ACCD;"> </span><span style="color:#C792EA;">extends</span><span style="color:#A6ACCD;"> </span><span style="color:#FFCB6B;">Model</span></span>
<span class="line"><span style="color:#89DDFF;">{</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#C792EA;">public</span><span style="color:#A6ACCD;"> </span><span style="color:#C792EA;">function</span><span style="color:#A6ACCD;"> </span><span style="color:#82AAFF;">author</span><span style="color:#89DDFF;">()</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#89DDFF;">{</span></span>
<span class="line"><span style="color:#A6ACCD;">        </span><span style="color:#89DDFF;font-style:italic;">return</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">$this-&gt;</span><span style="color:#82AAFF;">embedsOne</span><span style="color:#89DDFF;">(</span><span style="color:#FFCB6B;">Author</span><span style="color:#89DDFF;">::</span><span style="color:#F78C6C;">class</span><span style="color:#89DDFF;">);</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#89DDFF;">}</span></span>
<span class="line"><span style="color:#89DDFF;">}</span></span>
<span class="line"></span></code></pre></div><p>You can access the embedded models through the dynamic property:</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">book </span><span style="color:#89DDFF;">=</span><span style="color:#A6ACCD;"> </span><span style="color:#FFCB6B;">Book</span><span style="color:#89DDFF;">::</span><span style="color:#82AAFF;">first</span><span style="color:#89DDFF;">();</span></span>
<span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">author </span><span style="color:#89DDFF;">=</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">book</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#A6ACCD;">author</span><span style="color:#89DDFF;">;</span></span>
<span class="line"></span></code></pre></div><p>Inserting and updating embedded models works similar to the <code>hasOne</code> relation:</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">author </span><span style="color:#89DDFF;">=</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">book</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#82AAFF;">author</span><span style="color:#89DDFF;">()-&gt;</span><span style="color:#82AAFF;">save</span><span style="color:#89DDFF;">(</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#F78C6C;">new</span><span style="color:#A6ACCD;"> </span><span style="color:#FFCB6B;">Author</span><span style="color:#89DDFF;">([</span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">name</span><span style="color:#89DDFF;">&#39;</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">=&gt;</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">John Doe</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">])</span></span>
<span class="line"><span style="color:#89DDFF;">);</span></span>
<span class="line"></span>
<span class="line"><span style="color:#676E95;font-style:italic;">// Similar</span></span>
<span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">author </span><span style="color:#89DDFF;">=</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">book</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#82AAFF;">author</span><span style="color:#89DDFF;">()</span></span>
<span class="line"><span style="color:#A6ACCD;">         </span><span style="color:#89DDFF;">-&gt;</span><span style="color:#82AAFF;">create</span><span style="color:#89DDFF;">([</span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">name</span><span style="color:#89DDFF;">&#39;</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">=&gt;</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">John Doe</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">]);</span></span>
<span class="line"></span></code></pre></div><p>You can update the embedded model using the <code>save</code> method (available since release 2.0.0):</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">author </span><span style="color:#89DDFF;">=</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">book</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#A6ACCD;">author</span><span style="color:#89DDFF;">;</span></span>
<span class="line"></span>
<span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">author</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#A6ACCD;">name </span><span style="color:#89DDFF;">=</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">Jane Doe</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">;</span></span>
<span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">author</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#82AAFF;">save</span><span style="color:#89DDFF;">();</span></span>
<span class="line"></span></code></pre></div><p>You can replace the embedded model with a new model like this:</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">newAuthor </span><span style="color:#89DDFF;">=</span><span style="color:#A6ACCD;"> </span><span style="color:#F78C6C;">new</span><span style="color:#A6ACCD;"> </span><span style="color:#FFCB6B;">Author</span><span style="color:#89DDFF;">([</span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">name</span><span style="color:#89DDFF;">&#39;</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">=&gt;</span><span style="color:#A6ACCD;"> </span><span style="color:#89DDFF;">&#39;</span><span style="color:#C3E88D;">Jane Doe</span><span style="color:#89DDFF;">&#39;</span><span style="color:#89DDFF;">]);</span></span>
<span class="line"></span>
<span class="line"><span style="color:#89DDFF;">$</span><span style="color:#A6ACCD;">book</span><span style="color:#89DDFF;">-&gt;</span><span style="color:#82AAFF;">author</span><span style="color:#89DDFF;">()-&gt;</span><span style="color:#82AAFF;">save</span><span style="color:#89DDFF;">($</span><span style="color:#A6ACCD;">newAuthor</span><span style="color:#89DDFF;">);</span></span>
<span class="line"></span></code></pre></div>`,11),e=[o];function t(c,r,D,F,y,C){return n(),a("div",null,e)}const d=s(p,[["render",t]]);export{A as __pageData,d as default};
