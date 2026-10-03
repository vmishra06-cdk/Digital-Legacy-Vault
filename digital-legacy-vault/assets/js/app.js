document.addEventListener("mousemove", e=>{
document.body.style.backgroundPosition =
`${e.clientX/50}px ${e.clientY/50}px`;
});
