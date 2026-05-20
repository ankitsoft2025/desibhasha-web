from PIL import Image
"""
water-mark.py
Module: Adds a diagonal, rotated text watermark to images using Pillow.
Pip dependency:
    - Pillow
Public functions:
    - add_text_watermark(image, text, position, font_size=72, color=(255, 255, 255, 128))
        Add a rotated text watermark to a Pillow Image instance.
        Parameters
            image (PIL.Image.Image): Opened Pillow image (should be RGBA or will be treated as such when pasting).
            text (str): Watermark text to draw.
            position (tuple[int, int]): Top-left position (x, y) in pixels where the text image is placed before rotation.
            font_size (int, optional): Size of the TrueType font to use. Default: 72.
            color (tuple[int, int, int] or tuple[int, int, int, int], optional): RGB or RGBA fill for the text.
                If provided as RGB, alpha will be treated as semi-transparent by default.
                Default: (255, 255, 255, 128).
        Returns
            PIL.Image.Image: The modified image with the rotated watermark pasted on top.
        Notes
            - This function creates a separate RGBA image for the text, draws the text, rotates that image
              (45 degrees by default in the original implementation), and pastes it onto the target image
              using the text image as an alpha mask.
            - A TrueType font file (e.g., "arial.ttf") must be available; otherwise ImageFont.truetype will raise an OSError.
            - Opacity is extracted from the color tuple when length == 4; otherwise a default semi-transparent alpha is used.
    - update_image_watermark(input_folder, image_name, output_folder, water_mark_text="Desi bhasha", color="custom")
        Open an image file from disk, apply a text watermark, and save the result.
        Parameters
            input_folder (str): Path to folder containing the source image.
            image_name (str): Filename of the image to process.
            output_folder (str): Path to destination folder where watermarked image will be saved.
            water_mark_text (str, optional): Text to use for the watermark. Default: "Desi bhasha".
            color (str, optional): Key name into color_dict specifying RGBA color and alpha. Default: "custom".
        Returns
            None
        Side effects
            - Creates output_folder if it does not exist.
            - Saves the watermarked image to os.path.join(output_folder, image_name).
            - Prints a confirmation message with the save path.
        Raises
            - KeyError: if the provided color key is not present in the module's color_dict.
            - OSError (from Pillow): if the image file cannot be opened or saved, or if the font file cannot be loaded.
Module constants:
    - color_dict (dict): Preset mapping of color names to RGBA tuples used for watermark colors.
        Example keys present in this module: "white", "black", "red", "custom", "green", "blue", "gray".
Supported input file extensions (as used by the script loop):
    - .png, .jpg, .jpeg, .bmp, .gif
Typical usage example (from command line invocation):
    - The module can be run as a script to process all images in a given input folder:
        update_image_watermark("AnimalsInsects_Deepai", "example.jpg", "output", water_mark_text="© My Watermark", color="white")
Implementation notes and caveats:
    - The watermark placement and font size are computed relative to image dimensions in the script's logic
      (position and font_size derived from image.width and image.height). Adjust calculations for different layouts.
    - Rotation is performed with Image.rotate(..., expand=1) which may increase the temporary text image size.
    - Ensure the chosen font file path/name is valid on the target system (bundling or providing a fallback is recommended).
"""
from PIL import ImageDraw, ImageFont
import os

# add text watermark
def add_text_watermark(image, text, position, font_size=72, color=(255, 255, 255, 128)):
    drawable = ImageDraw.Draw(image)
    font = ImageFont.truetype("arial.ttf", font_size)
    # get opacity from function argument color
    opacity = color[3] if len(color) == 4 else 128
    #ROTATATE TEXT TO 45 DEGREES
    # To rotate text, we need to create a separate image for the text, rotate it, and then paste it onto the original image
    text_image = Image.new('RGBA', image.size, (255, 255, 255, 0))
    text_draw = ImageDraw.Draw(text_image)
    text_draw.text(position, text, font=font, fill=color)
    rotated_text = text_image.rotate(45, expand=1)
    image.paste(rotated_text, (0, 0), rotated_text)
    return image

color_dict = {
    "white": (255, 255, 255, 128),
    "black": (0, 0, 0, 128), # type: ignore
    "red": (255, 0, 0, 128),
    "custom": (235, 208, 214, 204),
    "green": (0, 255, 0, 128),
    "blue": (0, 0, 255, 128), "gray": (128, 128, 128, 256)
}

def update_image_watermark(input_folder, image_name, output_folder, water_mark_text = "Desi bhasha", color = "custom"):
    im = Image.open(os.path.join(input_folder, image_name))
    color = color_dict[color] # type: ignore
    # Get size of image make position relative to size and diagonally start from bottomleft to up right
    position = (im.width//6.5, im.height//10)

    font_size = im.width // 6.5

    watermarked_image = add_text_watermark(im, water_mark_text, position=position, font_size=font_size, color=color)
    # watermarked_image.show()
    save_path = os.path.join(output_folder, image_name.lower())
    os.makedirs(output_folder, exist_ok=True)
    watermarked_image.save(save_path)
    print(f"Watermarked image saved to {save_path}")

if __name__ == "__main__":
    # traverse all images in input folder
    input_folder = "input"
    output_folder = "output"
    for image_name in os.listdir(input_folder):
        if image_name.lower().endswith((".png", ".jpg", ".jpeg", ".bmp", ".gif")):
            update_image_watermark(input_folder, image_name, output_folder)