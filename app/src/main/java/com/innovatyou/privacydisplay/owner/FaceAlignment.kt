package com.innovatyou.privacydisplay.owner

import kotlin.math.sqrt

/**
 * Face alignment for the SFace recognition model: a similarity transform (rotation, uniform scale,
 * translation) that maps five facial landmarks onto the model's 112x112 template, computed with
 * the Umeyama least-squares method. Matches OpenCV's FaceRecognizerSF.alignCrop.
 */
object FaceAlignment {
    const val SIZE = 112

    /**
     * Template positions in the 112x112 crop, in image order: eye on the image left, eye on the
     * image right, nose, mouth corner on the image left, mouth corner on the image right.
     */
    val TEMPLATE = floatArrayOf(
        38.2946f, 51.6963f,
        73.5318f, 51.5014f,
        56.0252f, 71.7366f,
        41.5493f, 92.3655f,
        70.7299f, 92.2041f,
    )

    /**
     * Returns the 2x3 affine matrix [a, b, tx, c, d, ty] (row major) mapping [src] points onto
     * [dst] points, both given as x0, y0, x1, y1, ...
     */
    fun similarityTransform(src: FloatArray, dst: FloatArray = TEMPLATE): FloatArray {
        require(src.size == dst.size && src.size % 2 == 0 && src.size >= 4)
        val n = src.size / 2
        var msx = 0.0; var msy = 0.0; var mdx = 0.0; var mdy = 0.0
        for (i in 0 until n) {
            msx += src[2 * i]; msy += src[2 * i + 1]
            mdx += dst[2 * i]; mdy += dst[2 * i + 1]
        }
        msx /= n; msy /= n; mdx /= n; mdy /= n

        // Covariance A = (1/n) * sum(dst_c * src_c^T) and source variance.
        var a00 = 0.0; var a01 = 0.0; var a10 = 0.0; var a11 = 0.0; var variance = 0.0
        for (i in 0 until n) {
            val sx = src[2 * i] - msx; val sy = src[2 * i + 1] - msy
            val dx = dst[2 * i] - mdx; val dy = dst[2 * i + 1] - mdy
            a00 += dx * sx; a01 += dx * sy; a10 += dy * sx; a11 += dy * sy
            variance += sx * sx + sy * sy
        }
        a00 /= n; a01 /= n; a10 /= n; a11 /= n; variance /= n

        // For 2D the optimal rotation (with uniform scale) has the closed form below; it is the
        // Umeyama solution, without needing a general SVD.
        val p = a00 + a11
        val q = a10 - a01
        val norm = sqrt(p * p + q * q)
        if (norm == 0.0 || variance == 0.0) return floatArrayOf(1f, 0f, 0f, 0f, 1f, 0f)
        val cos = p / norm
        val sin = q / norm
        val scale = norm / variance

        val a = scale * cos
        val b = -scale * sin
        val c = scale * sin
        val d = scale * cos
        val tx = mdx - (a * msx + b * msy)
        val ty = mdy - (c * msx + d * msy)
        return floatArrayOf(a.toFloat(), b.toFloat(), tx.toFloat(), c.toFloat(), d.toFloat(), ty.toFloat())
    }

    /** Converts ARGB pixels of the 112x112 aligned face to the model input: RGB, 0–255, NCHW. */
    fun toModelInput(pixels: IntArray): FloatArray {
        val area = SIZE * SIZE
        require(pixels.size == area)
        val out = FloatArray(3 * area)
        for (i in 0 until area) {
            val p = pixels[i]
            out[i] = ((p shr 16) and 0xFF).toFloat()
            out[area + i] = ((p shr 8) and 0xFF).toFloat()
            out[2 * area + i] = (p and 0xFF).toFloat()
        }
        return out
    }
}
