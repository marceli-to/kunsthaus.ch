import { ref } from 'vue';

// Detect WebGPU once (cleaner/faster on capable devices, WASM fallback else).
let webgpuSupport = null;

async function hasWebGpu() {
	if (webgpuSupport !== null) return webgpuSupport;
	try {
		webgpuSupport = !!navigator.gpu && !!(await navigator.gpu.requestAdapter());
	} catch {
		webgpuSupport = false;
	}
	return webgpuSupport;
}

// Client-side background removal via @imgly.
// PROD: @imgly/background-removal is AGPL-3.0 — buy the commercial licence or
// swap to a permissive model (BiRefNet/MODNet) before launch. The raw photo
// never leaves the device: only the finished cut-out is uploaded.
export function useBackgroundRemoval() {
	const cutoutBusy = ref(false);
	const cutoutProgress = ref(0);

	// Resolves to a transparent PNG blob, or throws so the caller can fall back
	// to the original file.
	async function removeBackgroundFrom(file) {
		cutoutBusy.value = true;
		cutoutProgress.value = 0;
		try {
			const { removeBackground } = await import('@imgly/background-removal');
			const gpu = await hasWebGpu();
			const weakCpu = !gpu && (navigator.hardwareConcurrency ?? 4) < 8;
			return await removeBackground(file, {
				model: weakCpu ? 'isnet_fp16' : 'isnet',
				device: gpu ? 'gpu' : 'cpu',
				output: { format: 'image/png', quality: 1 },
				progress: (_key, current, total) => {
					cutoutProgress.value = total ? Math.round((current / total) * 100) : 0;
				},
			});
		} finally {
			cutoutBusy.value = false;
		}
	}

	return { cutoutBusy, cutoutProgress, removeBackgroundFrom };
}
