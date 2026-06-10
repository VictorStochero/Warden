{{-- Supplemental utilities not in the purged bundle. The dashboard ships a
     prebuilt CSS (no build step), so classes used in markup added after that
     build live here, mirroring Tailwind's standard output. Shared by the
     layout and the standalone login page so both stay in parity — add any new
     gap-filler class here once. --}}
.z-50{z-index:50}
.inset-0{inset:0}
.justify-center{justify-content:center}
.-mx-1{margin-left:-.25rem;margin-right:-.25rem}
.p-6{padding:1.5rem}
.w-5{width:1.25rem}.h-5{height:1.25rem}
.max-w-md{max-width:28rem}.max-w-sm{max-width:24rem}
.max-h-40{max-height:10rem}
.overflow-auto{overflow:auto}
.whitespace-pre-wrap{white-space:pre-wrap}
.leading-relaxed{line-height:1.625}
.shadow-xl{box-shadow:0 20px 25px -5px rgba(0,0,0,.25),0 8px 10px -6px rgba(0,0,0,.25)}
.bg-black\/60{background-color:rgba(0,0,0,.6)}
.bg-emerald-500\/10{background-color:rgba(16,185,129,.1)}
.text-emerald-200{color:#a7f3d0}
.text-rose-200{color:#fecdd3}
.border-emerald-500{border-color:#10b981}
.border-emerald-700\/60{border-color:rgba(4,120,87,.6)}
.border-rose-700\/60{border-color:rgba(190,18,60,.6)}
.bg-amber-900\/20{background-color:rgba(120,53,15,.2)}
.text-amber-100{color:#fef3c7}
.text-amber-200{color:#fde68a}
.text-amber-300{color:#fcd34d}
.border-amber-600\/60{border-color:rgba(217,119,6,.6)}
.border-amber-700\/50{border-color:rgba(180,83,9,.5)}
.hover\:bg-rose-500\/10:hover{background-color:rgba(244,63,94,.1)}
.hover\:bg-amber-600\/20:hover{background-color:rgba(217,119,6,.2)}
.hover\:border-emerald-500:hover{border-color:#10b981}
.hover\:border-rose-500:hover{border-color:#f43f5e}
.hover\:border-slate-500:hover{border-color:#64748b}
.hover\:text-emerald-200:hover{color:#a7f3d0}
.hover\:text-rose-200:hover{color:#fecdd3}
.hover\:text-amber-200:hover{color:#fde68a}
.hover\:text-brand-300:hover{color:#a5b4fc}
.hover\:text-slate-300:hover{color:#cbd5e1}
@media (min-width:640px){.sm\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (min-width:1280px){.xl\:grid-cols-8{grid-template-columns:repeat(8,minmax(0,1fr))}}
